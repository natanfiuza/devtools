<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Isolamento entre as abilities da API (F8, ADR-088).
|
| `ai:query` e `badgeforge:read|write` sao ortogonais: o grupo de rotas de
| cada superficie exige a sua, e um token nunca herda o acesso da outra por
| pertencer a mesma conta. A escrita tambem nao implica leitura.
*/

/**
 * @param  list<string>  $abilities
 */
function isolationToken(User $user, array $abilities): string
{
    return $user->createToken('isolation', $abilities)->plainTextToken;
}

it('token de ai:query lê /api/v1/me mas não a API do BadgeForge', function () {
    $user = User::factory()->create();
    $token = isolationToken($user, ['ai:query']);

    $this->withToken($token)->getJson(route('api.v1.me'))->assertOk();

    $this->withToken($token)
        ->getJson(route('api.v1.badgeforge.badges.index'))
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});

it('token de badgeforge:read lê o BadgeForge mas não a API de consultas', function () {
    $user = User::factory()->create();
    $token = isolationToken($user, ['badgeforge:read']);

    $this->withToken($token)
        ->getJson(route('api.v1.badgeforge.badges.index'))
        ->assertOk();

    $this->withToken($token)
        ->getJson(route('api.v1.me'))
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});

it('a ability de escrita não implica leitura', function () {
    $user = User::factory()->create();
    $token = isolationToken($user, ['badgeforge:write']);

    $this->withToken($token)
        ->getJson(route('api.v1.badgeforge.badges.index'))
        ->assertStatus(403);

    $this->withToken($token)
        ->postJson(route('api.v1.badgeforge.badges.store'), ['name' => 'sem'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});
