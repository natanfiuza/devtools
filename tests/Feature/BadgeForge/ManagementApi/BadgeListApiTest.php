<?php

use App\Models\BadgeAsset;
use App\Models\BadgeCustomLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Leitura das colecoes do dono na API de gestao (F8, ADR-088).
|
| `badgeforge:read` abre a lista de logos e de badges pessoais. O escopo e
| sempre a conta do token: a lista de outra conta nunca aparece, e recurso
| alheio responde 404 (nunca 403) nas rotas de escrita.
*/

/**
 * @param  list<string>  $abilities
 */
function mgmtApiToken(User $user, array $abilities = ['badgeforge:read']): string
{
    return $user->createToken('management', $abilities)->plainTextToken;
}

it('exige token nas listas autenticadas', function () {
    $this->getJson(route('api.v1.badgeforge.logos.index'))->assertStatus(401);
    $this->getJson(route('api.v1.badgeforge.badges.index'))->assertStatus(401);
});

it('recusa o token de ai:query nas rotas de gestão', function () {
    $user = User::factory()->create();

    $this->withToken(mgmtApiToken($user, ['ai:query']))
        ->getJson(route('api.v1.badgeforge.badges.index'))
        ->assertStatus(403);
});

it('lista só as badges do dono do token, paginado e sem credencial', function () {
    $user = User::factory()->create(['username' => 'octocat']);
    $other = User::factory()->create(['username' => 'someone']);

    BadgeAsset::factory()->for($user)->count(3)->sequence(
        fn ($sequence): array => ['name' => 'badge-'.$sequence->index],
    )->create();
    BadgeAsset::factory()->for($other)->create(['name' => 'other']);

    $response = $this->withToken(mgmtApiToken($user))
        ->getJson(route('api.v1.badgeforge.badges.index', ['per_page' => 2]))
        ->assertOk();

    $payload = $response->json();

    expect($payload['data'])->toHaveCount(2)
        ->and($payload['meta'])->toMatchArray([
            'current_page' => 1,
            'per_page' => 2,
            'total' => 3,
            'last_page' => 2,
        ])
        ->and($payload['links']['next'])->toBeString();

    $badge = $payload['data'][0];

    expect($badge)->toHaveKeys([
        'id', 'name', 'type', 'providerSlug', 'definitionSlug', 'params',
        'private', 'credentialSet', 'credentialUsername', 'url', 'pngUrl',
        'lastGeneratedAt', 'expiresAt', 'createdAt',
    ])
        ->and($badge['url'])->toEndWith('/b/octocat/'.$badge['id'].'.svg')
        ->and($badge['pngUrl'])->toEndWith('/b/octocat/'.$badge['id'].'.png');

    $ids = array_column($payload['data'], 'id');
    $otherId = (string) BadgeAsset::query()->where('user_id', $other->id)->sole()->id;

    expect((string) $response->getContent())
        ->not->toContain('"credential"')
        ->and($ids)->not->toContain($otherId);
});

it('lista só os logos do dono, com metadados e sem paths', function () {
    $user = User::factory()->create();

    BadgeCustomLogo::factory()->for($user)->create(['name' => 'Zed']);
    BadgeCustomLogo::factory()->for($user)->create(['name' => 'alpha']);
    BadgeCustomLogo::factory()->create();

    $response = $this->withToken(mgmtApiToken($user))
        ->getJson(route('api.v1.badgeforge.logos.index'))
        ->assertOk();

    $payload = $response->json();

    expect($payload['data'])->toHaveCount(2)
        ->and($payload['meta'])->toMatchArray(['limit' => 2, 'used' => 2]);

    // Ordem estavel por nome sem caixa, independente da collation.
    expect(array_column($payload['data'], 'name'))->toBe(['alpha', 'Zed']);

    $logo = $payload['data'][0];

    expect($logo)->toHaveKeys(['id', 'slug', 'name', 'viewBox', 'pathCount', 'sizeBytes', 'createdAt'])
        ->and($logo['slug'])->toBe('u_'.$logo['id']);

    expect((string) $response->getContent())->not->toContain('"paths"');
});
