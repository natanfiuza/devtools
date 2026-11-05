<?php

use App\Models\BadgeAsset;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/*
| Escrita de badges pessoais pela API de gestao (F8, ADR-088).
|
| As regras continuam nas actions: plano pago, username, provider e
| credencial. A API so troca o `back()` da tela por 201 e o envelope.
*/

const BADGE_WRITE_API_DIR = 'framework/testing/badgeforge-write-api';

const BADGE_WRITE_API_TOKEN = 'ghp_api_secreto_7c1d';

beforeEach(function () {
    config(['badgeforge.personal_badges.path' => BADGE_WRITE_API_DIR]);

    File::deleteDirectory(storage_path(BADGE_WRITE_API_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(BADGE_WRITE_API_DIR));
});

function badgeWriteApiToken(User $user, array $abilities = ['badgeforge:write']): string
{
    return $user->createToken('management-write', $abilities)->plainTextToken;
}

function badgeWriteApiAccount(string $username = 'octocat', bool $paid = true): User
{
    $user = User::factory()->create(['username' => $username]);

    if ($paid) {
        $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

        Subscription::factory()->for($user)->for($plan)->create([
            'current_period_start' => now()->subWeek(),
            'current_period_end' => now()->addWeeks(3),
        ]);
    }

    return $user->refresh();
}

/**
 * @return array<string, mixed>
 */
function badgeWriteApiForm(): array
{
    return [
        'label' => 'build',
        'message' => 'passing',
        'style' => 'flat',
        'color' => '#44cc11',
        'labelColor' => '#555555',
    ];
}

it('cria uma badge estática e devolve a URL pública', function () {
    $user = badgeWriteApiAccount();

    $response = $this->withToken(badgeWriteApiToken($user))
        ->postJson(route('api.v1.badgeforge.badges.store'), [
            'name' => 'Build da API',
            'type' => 'static',
            'form' => badgeWriteApiForm(),
        ])
        ->assertCreated();

    $badge = $response->json('data');

    expect($badge)->toHaveKeys(['id', 'name', 'type', 'private', 'credentialSet', 'url', 'pngUrl'])
        ->and($badge['name'])->toBe('Build da API')
        ->and($badge['type'])->toBe('static')
        ->and($badge['credentialSet'])->toBeFalse()
        ->and($badge['url'])->toEndWith('/b/octocat/'.$badge['id'].'.svg');

    expect(BadgeAsset::query()->whereKey($badge['id'])->exists())->toBeTrue();
});

it('recusa a criação para conta Free no envelope', function () {
    $user = badgeWriteApiAccount(paid: false);

    $this->withToken(badgeWriteApiToken($user))
        ->postJson(route('api.v1.badgeforge.badges.store'), [
            'name' => 'Free',
            'type' => 'static',
            'form' => badgeWriteApiForm(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.errors.badge.0', __('validation.badge_asset.paid_only'));

    expect($user->badgeAssets()->count())->toBe(0);
});

it('grava a credencial cifrada sem devolvê-la na resposta', function () {
    $user = badgeWriteApiAccount();

    $response = $this->withToken(badgeWriteApiToken($user))
        ->postJson(route('api.v1.badgeforge.badges.store'), [
            'name' => 'Repo privado',
            'type' => 'provider',
            'provider_slug' => 'github',
            'definition_slug' => 'stars',
            'params' => ['owner' => 'octocat', 'repo' => 'privado'],
            'private' => true,
            'credential' => BADGE_WRITE_API_TOKEN,
        ])
        ->assertCreated();

    expect($response->json('data.credentialSet'))->toBeTrue()
        ->and((string) $response->getContent())->not->toContain(BADGE_WRITE_API_TOKEN)
        ->and((string) $response->getContent())->not->toContain('"credential"');
});

it('exclui a própria badge e responde 404 para a de outra conta', function () {
    $user = badgeWriteApiAccount();
    $asset = BadgeAsset::factory()->for($user)->create(['name' => 'minha']);

    $this->withToken(badgeWriteApiToken($user))
        ->deleteJson(route('api.v1.badgeforge.badges.destroy', ['badgeAsset' => $asset->id]))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => (string) $asset->id, 'deleted' => true]]);

    expect(BadgeAsset::query()->whereKey($asset->id)->exists())->toBeFalse();

    $foreign = BadgeAsset::factory()->create();

    $this->withToken(badgeWriteApiToken($user))
        ->deleteJson(route('api.v1.badgeforge.badges.destroy', ['badgeAsset' => $foreign->id]))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    expect(BadgeAsset::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('recusa um token só de leitura na escrita de badges', function () {
    $user = badgeWriteApiAccount();

    $this->withToken(badgeWriteApiToken($user, ['badgeforge:read']))
        ->postJson(route('api.v1.badgeforge.badges.store'), [
            'name' => 'Sem permissão',
            'type' => 'static',
            'form' => badgeWriteApiForm(),
        ])
        ->assertStatus(403);

    expect($user->badgeAssets()->count())->toBe(0);
});
