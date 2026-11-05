<?php

use App\Models\BadgeAsset;
use App\Models\BadgeCustomLogo;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeEndpoint;
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

it('token de escrita não lê nenhuma listagem da API do BadgeForge', function () {
    $user = User::factory()->create();
    $token = isolationToken($user, ['badgeforge:write']);

    foreach ([
        'api.v1.badgeforge.logos.index',
        'api.v1.badgeforge.badges.index',
        'api.v1.badgeforge.endpoints.index',
        'api.v1.badgeforge.custom-providers.index',
        'api.v1.badgeforge.analytics',
    ] as $name) {
        $this->withToken($token)
            ->getJson(route($name))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }
});

it('token de leitura não executa nenhuma escrita da API do BadgeForge', function () {
    $user = User::factory()->create();
    $token = isolationToken($user, ['badgeforge:read']);

    $asset = BadgeAsset::factory()->for($user)->create();
    $logo = BadgeCustomLogo::factory()->for($user)->create();
    $endpoint = BadgeEndpoint::factory()->for($user)->create();
    $provider = BadgeCustomProvider::factory()->for($user)->create();

    $calls = [
        ['patchJson', route('api.v1.badgeforge.badges.update', ['badgeAsset' => $asset->id]), ['name' => 'outro']],
        ['patchJson', route('api.v1.badgeforge.badges.credential', ['badgeAsset' => $asset->id]), ['credential' => 'ghp_x']],
        ['postJson', route('api.v1.badgeforge.badges.refresh', ['badgeAsset' => $asset->id]), []],
        ['patchJson', route('api.v1.badgeforge.logos.update', ['badgeCustomLogo' => $logo->id]), ['name' => 'outro']],
        ['postJson', route('api.v1.badgeforge.endpoints.store'), []],
        ['patchJson', route('api.v1.badgeforge.endpoints.update', ['badgeEndpoint' => $endpoint->id]), ['name' => 'outro']],
        ['deleteJson', route('api.v1.badgeforge.endpoints.destroy', ['badgeEndpoint' => $endpoint->id]), []],
        ['postJson', route('api.v1.badgeforge.custom-providers.store'), []],
        ['patchJson', route('api.v1.badgeforge.custom-providers.update', ['badgeCustomProvider' => $provider->id]), []],
        ['deleteJson', route('api.v1.badgeforge.custom-providers.destroy', ['badgeCustomProvider' => $provider->id]), []],
    ];

    foreach ($calls as [$method, $url, $payload]) {
        $this->withToken($token)
            ->{$method}($url, $payload)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }
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
