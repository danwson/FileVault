<?php

use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Troca o disco "s3" por um fake local: os testes exercitam o mesmo
    // código de produção (Storage::disk('s3')), mas sem depender de um
    // MinIO real rodando — mantém a suite rápida e isolada, no mesmo
    // espírito do SQLite em memória usado pro banco.
    Storage::fake('s3');
});

// Inclui sempre "Accept: application/json": sem isso, o Laravel trata a
// requisição como um form web tradicional e responde falhas de auth/
// validação com redirect (302) em vez de JSON — mascarando os status
// codes que essa suite está verificando.
function authHeader(User $user): array
{
    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$user->createToken('api')->plainTextToken,
    ];
}

function jsonHeader(): array
{
    return ['Accept' => 'application/json'];
}

test('usuário autenticado consegue fazer upload de um arquivo', function () {
    $user = User::factory()->create();
    $upload = UploadedFile::fake()->create('relatorio.pdf', 500, 'application/pdf');

    $response = $this->withHeaders(authHeader($user))
        ->post('/api/files', ['file' => $upload]);

    $response->assertCreated()
        ->assertJsonPath('original_name', 'relatorio.pdf')
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonPath('size', $upload->getSize());

    $file = File::first();
    expect($file)->not->toBeNull();
    Storage::disk('s3')->assertExists($file->storage_path);
});

test('upload exige autenticação', function () {
    $upload = UploadedFile::fake()->create('arquivo.txt', 100);

    $response = $this->withHeaders(jsonHeader())
        ->post('/api/files', ['file' => $upload]);

    $response->assertStatus(401);
});

test('upload sem arquivo anexado falha na validação', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(authHeader($user))
        ->postJson('/api/files', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('file');

    expect(File::count())->toBe(0);
});

test('upload de arquivo maior que 10MB falha na validação', function () {
    $user = User::factory()->create();
    // 10240 KB = 10MB é o limite (StoreFileRequest); 10241 KB excede.
    $upload = UploadedFile::fake()->create('grande.zip', 10241);

    $response = $this->withHeaders(authHeader($user))
        ->post('/api/files', ['file' => $upload]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('file');

    expect(File::count())->toBe(0);
});

test('listagem retorna só os arquivos do usuário autenticado', function () {
    $user = User::factory()->create();
    $outroUsuario = User::factory()->create();

    File::factory()->count(2)->create(['user_id' => $user->id]);
    File::factory()->count(3)->create(['user_id' => $outroUsuario->id]);

    $response = $this->withHeaders(authHeader($user))
        ->getJson('/api/files');

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    collect($response->json('data'))->each(
        fn ($file) => expect($file['user_id'])->toBe($user->id)
    );
});

test('dono consegue ver o detalhe do próprio arquivo', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);

    $response = $this->withHeaders(authHeader($user))
        ->getJson("/api/files/{$file->id}");

    $response->assertOk()
        ->assertJsonPath('id', $file->id)
        ->assertJsonPath('original_name', $file->original_name);
});

test('usuário não consegue ver detalhe de arquivo de outro usuário', function () {
    $dono = User::factory()->create();
    $outroUsuario = User::factory()->create();
    $file = File::factory()->create(['user_id' => $dono->id]);

    $response = $this->withHeaders(authHeader($outroUsuario))
        ->getJson("/api/files/{$file->id}");

    $response->assertStatus(403);
});

test('usuário não consegue deletar arquivo de outro usuário', function () {
    Storage::disk('s3')->put('files/1/protegido.txt', 'conteudo');

    $dono = User::factory()->create();
    $outroUsuario = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $dono->id,
        'storage_path' => 'files/1/protegido.txt',
    ]);

    $response = $this->withHeaders(authHeader($outroUsuario))
        ->deleteJson("/api/files/{$file->id}");

    $response->assertStatus(403);

    $this->assertDatabaseHas('files', ['id' => $file->id]);
    Storage::disk('s3')->assertExists('files/1/protegido.txt');
});

test('deletar arquivo remove do storage e do banco', function () {
    Storage::disk('s3')->put('files/1/remover.txt', 'conteudo');

    $user = User::factory()->create();
    $file = File::factory()->create([
        'user_id' => $user->id,
        'storage_path' => 'files/1/remover.txt',
    ]);

    $response = $this->withHeaders(authHeader($user))
        ->deleteJson("/api/files/{$file->id}");

    $response->assertStatus(204);

    $this->assertDatabaseMissing('files', ['id' => $file->id]);
    Storage::disk('s3')->assertMissing('files/1/remover.txt');
});
