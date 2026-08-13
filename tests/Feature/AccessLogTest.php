<?php

use App\Enums\AccessEventType;
use App\Jobs\LogAccessJob;
use App\Models\AccessLog;
use App\Models\File;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path, $expiration) => "https://fake-presigned.test/{$path}?expires={$expiration->timestamp}"
    );
});

test('upload gera um AccessLog do tipo upload', function () {
    $user = User::factory()->create();
    $upload = UploadedFile::fake()->create('relatorio.pdf', 200, 'application/pdf');

    $this->withHeaders(authHeader($user))
        ->post('/api/files', ['file' => $upload])
        ->assertCreated();

    $file = File::first();

    // QUEUE_CONNECTION=sync em ambiente de teste (phpunit.xml): o job
    // roda no mesmo request, então já dá pra checar o AccessLog direto.
    // count() === 1 (não só ">= 1") de propósito: já pegamos uma
    // regressão real aqui — LogAccessListener registrado tanto via
    // auto-discovery (pela interface AccessLoggable) quanto manualmente
    // no AppServiceProvider fazia o evento disparar 2x, duplicando o log.
    expect(AccessLog::count())->toBe(1);

    $log = AccessLog::first();
    expect($log->file_id)->toBe($file->id);
    expect($log->share_link_id)->toBeNull();
    expect($log->event_type)->toBe(AccessEventType::Upload);
    expect($log->ip_address)->not->toBeEmpty();
});

test('o listener despacha LogAccessJob pra fila (verificação da arquitetura assíncrona)', function () {
    Queue::fake();

    $user = User::factory()->create();
    $upload = UploadedFile::fake()->create('arquivo.txt', 50);

    $this->withHeaders(authHeader($user))->post('/api/files', ['file' => $upload]);

    Queue::assertPushed(LogAccessJob::class, function (LogAccessJob $job) {
        return $job->attributes['event_type'] === AccessEventType::Upload->value;
    });

    // Com a fila fake, o job nunca roda -> nenhum log deve ter sido
    // gravado de fato (prova que a gravação depende do worker, não do
    // request em si).
    expect(AccessLog::count())->toBe(0);
});

test('download autenticado gera um AccessLog do tipo download', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    Storage::disk('s3')->put($file->storage_path, 'conteudo');

    $response = $this->withHeaders(authHeader($user))
        ->get("/api/files/{$file->id}/download");

    $response->assertRedirect();

    $log = AccessLog::where('event_type', AccessEventType::Download)->first();
    expect($log)->not->toBeNull();
    expect($log->file_id)->toBe($file->id);
});

test('acesso válido a um share link gera um AccessLog do tipo share_access', function () {
    $file = File::factory()->create();
    Storage::disk('s3')->put($file->storage_path, 'conteudo');
    $shareLink = ShareLink::factory()->for($file)->create();

    $this->get("/api/share-links/{$shareLink->token}")->assertRedirect();

    $log = AccessLog::where('event_type', AccessEventType::ShareAccess)->first();
    expect($log)->not->toBeNull();
    expect($log->file_id)->toBe($file->id);
    expect($log->share_link_id)->toBe($shareLink->id);
});

test('acessar token inexistente (404) não gera log', function () {
    $this->getJson('/api/share-links/token-que-nao-existe')->assertStatus(404);

    expect(AccessLog::count())->toBe(0);
});

test('acessar link expirado ou esgotado (410) não gera log', function () {
    $expirado = ShareLink::factory()->expired()->create();
    $this->getJson("/api/share-links/{$expirado->token}")->assertStatus(410);

    $esgotado = ShareLink::factory()->create(['max_uses' => 1, 'access_count' => 1]);
    $this->getJson("/api/share-links/{$esgotado->token}")->assertStatus(410);

    expect(AccessLog::count())->toBe(0);
});

test('GET /api/files/{id}/logs retorna 403 para arquivo de outro usuário', function () {
    $dono = User::factory()->create();
    $outroUsuario = User::factory()->create();
    $file = File::factory()->create(['user_id' => $dono->id]);

    $this->withHeaders(authHeader($outroUsuario))
        ->getJson("/api/files/{$file->id}/logs")
        ->assertStatus(403);
});

test('logs retornam em ordem cronológica decrescente', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);

    $mais_antigo = AccessLog::factory()->for($file)->create(['created_at' => now()->subHours(2)]);
    $mais_recente = AccessLog::factory()->for($file)->create(['created_at' => now()]);
    $meio = AccessLog::factory()->for($file)->create(['created_at' => now()->subHour()]);

    $response = $this->withHeaders(authHeader($user))
        ->getJson("/api/files/{$file->id}/logs");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids->all())->toBe([$mais_recente->id, $meio->id, $mais_antigo->id]);
});

test('logs só mostram eventos do próprio arquivo, e paginação funciona com múltiplos eventos', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    $outroFile = File::factory()->create();

    AccessLog::factory()->for($file)->count(20)->create();
    AccessLog::factory()->for($outroFile)->count(3)->create();

    $response = $this->withHeaders(authHeader($user))
        ->getJson("/api/files/{$file->id}/logs");

    $response->assertOk()
        ->assertJsonCount(15, 'data') // tamanho de página padrão
        ->assertJsonPath('meta.total', 20)
        ->assertJsonPath('meta.last_page', 2);

    $segundaPagina = $this->withHeaders(authHeader($user))
        ->getJson("/api/files/{$file->id}/logs?page=2");
    $segundaPagina->assertOk()->assertJsonCount(5, 'data');
});

test('resposta de log expõe só os campos relevantes, sem vazar file_id/share_link_id', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);
    AccessLog::factory()->for($file)->create([
        'event_type' => AccessEventType::Upload->value,
        'ip_address' => '203.0.113.42',
        'user_agent' => 'curl/8.4.0',
    ]);

    $response = $this->withHeaders(authHeader($user))
        ->getJson("/api/files/{$file->id}/logs");

    $response->assertJsonStructure([
        'data' => [['id', 'event_type', 'ip_address', 'user_agent', 'created_at']],
    ]);
    $response->assertJsonMissingPath('data.0.file_id');
    $response->assertJsonMissingPath('data.0.share_link_id');
    $response->assertJsonPath('data.0.event_type', 'upload');
    $response->assertJsonPath('data.0.ip_address', '203.0.113.42');
});
