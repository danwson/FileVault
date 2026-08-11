<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

test('usuário consegue se registrar com dados válidos', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Daniel Alves',
        'email' => 'daniel@example.com',
        'password' => 'senha-forte-123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.name', 'Daniel Alves')
        ->assertJsonPath('user.email', 'daniel@example.com')
        ->assertJsonMissing(['password']);

    $this->assertDatabaseHas('users', [
        'email' => 'daniel@example.com',
    ]);
});

test('registro falha com email duplicado', function () {
    User::factory()->create(['email' => 'duplicado@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'Outro Usuário',
        'email' => 'duplicado@example.com',
        'password' => 'senha-forte-123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertDatabaseCount('users', 1);
});

test('registro falha com senha muito curta/fraca', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Daniel Alves',
        'email' => 'daniel@example.com',
        'password' => '123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('password');

    $this->assertDatabaseMissing('users', [
        'email' => 'daniel@example.com',
    ]);
});

test('usuário consegue fazer login com credenciais corretas', function () {
    User::factory()->create([
        'email' => 'daniel@example.com',
        'password' => bcrypt('senha-correta'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'daniel@example.com',
        'password' => 'senha-correta',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']])
        ->assertJsonPath('user.email', 'daniel@example.com');
});

test('login falha com senha errada', function () {
    User::factory()->create([
        'email' => 'daniel@example.com',
        'password' => bcrypt('senha-correta'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'daniel@example.com',
        'password' => 'senha-errada',
    ]);

    $response->assertStatus(401)
        ->assertJsonMissingPath('token');
});

test('rota /api/me retorna 401 sem token', function () {
    $response = $this->getJson('/api/me');

    $response->assertStatus(401);
});

test('rota /api/me retorna dados corretos com token válido', function () {
    $user = User::factory()->create([
        'name' => 'Daniel Alves',
        'email' => 'daniel@example.com',
    ]);
    $token = $user->createToken('api')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me');

    $response->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', 'Daniel Alves')
        ->assertJsonPath('email', 'daniel@example.com');
});

test('logout revoga o token (tentativa de uso posterior falha)', function () {
    User::factory()->create([
        'email' => 'daniel@example.com',
        'password' => bcrypt('senha-correta'),
    ]);

    $login = $this->postJson('/api/login', [
        'email' => 'daniel@example.com',
        'password' => 'senha-correta',
    ]);
    $token = $login->json('token');

    expect(PersonalAccessToken::count())->toBe(1);

    $logout = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout');
    $logout->assertOk();

    expect(PersonalAccessToken::count())->toBe(0);

    // Dentro de um mesmo teste, chamadas HTTP sequenciais reaproveitam a
    // mesma aplicação — o guard cacheia o usuário resolvido na chamada
    // anterior. Sem isso, a checagem abaixo passaria mesmo com o token já
    // revogado no banco, mascarando um cenário que numa requisição HTTP
    // real (processo novo a cada request) já funciona corretamente.
    Auth::forgetGuards();

    $reuse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me');
    $reuse->assertStatus(401);
});
