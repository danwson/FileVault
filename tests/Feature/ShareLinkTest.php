<?php

use App\Models\File;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');

    // O driver "local" (usado por Storage::fake) não implementa
    // temporaryUrl() nativamente — registramos um gerador fake pra poder
    // testar o redirect sem depender de MinIO/AWS real.
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path, $expiration) => "https://fake-presigned.test/{$path}?expires={$expiration->timestamp}"
    );
});

test('dono consegue criar link de compartilhamento pro próprio arquivo', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);

    $response = $this->withHeaders(authHeader($user))
        ->postJson("/api/files/{$file->id}/share-links", [
            'expires_in_minutes' => 60,
            'max_uses' => 5,
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'url', 'expires_at', 'max_uses', 'access_count'])
        ->assertJsonPath('max_uses', 5)
        ->assertJsonPath('access_count', 0);

    $this->assertDatabaseHas('share_links', ['file_id' => $file->id]);
});

test('usuário não consegue criar link para arquivo de outro usuário', function () {
    $dono = User::factory()->create();
    $outroUsuario = User::factory()->create();
    $file = File::factory()->create(['user_id' => $dono->id]);

    $response = $this->withHeaders(authHeader($outroUsuario))
        ->postJson("/api/files/{$file->id}/share-links", ['expires_in_minutes' => 60]);

    $response->assertStatus(403);
    $this->assertDatabaseCount('share_links', 0);
});

test('criar link exige expires_in_minutes válido', function () {
    $user = User::factory()->create();
    $file = File::factory()->create(['user_id' => $user->id]);

    $response = $this->withHeaders(authHeader($user))
        ->postJson("/api/files/{$file->id}/share-links", []);

    $response->assertStatus(422)->assertJsonValidationErrors('expires_in_minutes');
});

test('acessar token que nunca existiu retorna 404', function () {
    $response = $this->getJson('/api/share-links/token-inexistente-123');

    $response->assertStatus(404);
});

test('acessar link expirado retorna 410, não 404', function () {
    $shareLink = ShareLink::factory()->expired()->create();

    $response = $this->getJson("/api/share-links/{$shareLink->token}");

    $response->assertStatus(410);

    // Não conta como acesso válido — access_count não deve mudar.
    expect($shareLink->fresh()->access_count)->toBe(0);
});

test('link válido redireciona para a URL de download e incrementa access_count', function () {
    $shareLink = ShareLink::factory()->create(['max_uses' => null]);

    $response = $this->get("/api/share-links/{$shareLink->token}");

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('fake-presigned.test');
    expect($shareLink->fresh()->access_count)->toBe(1);
});

test('access_count nunca ultrapassa max_uses, e o excedente recebe 410', function () {
    $shareLink = ShareLink::factory()->create(['max_uses' => 3]);

    // 3 primeiros acessos: válidos, cada um incrementa o contador.
    for ($i = 1; $i <= 3; $i++) {
        $response = $this->get("/api/share-links/{$shareLink->token}");
        $response->assertRedirect();
        expect($shareLink->fresh()->access_count)->toBe($i);
    }

    // 4º acesso: limite já atingido -> 410, e o contador NÃO passa de 3.
    $fourth = $this->getJson("/api/share-links/{$shareLink->token}");
    $fourth->assertStatus(410);
    expect($shareLink->fresh()->access_count)->toBe(3);

    // 5º acesso, pra garantir que não é um caso isolado: continua 410,
    // contador continua travado em 3.
    $fifth = $this->getJson("/api/share-links/{$shareLink->token}");
    $fifth->assertStatus(410);
    expect($shareLink->fresh()->access_count)->toBe(3);
});
