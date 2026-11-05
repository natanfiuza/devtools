<?php

use App\Models\BadgeAsset;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\Personal\BadgeAssetStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Refresh de badge pessoal pela API de gestao (F9, ADR-090).
|
| O POST e sincrono e dispara a chamada externa; por isso empilha o limite da
| tela (`badgeforge-refresh`, 10/min por conta) com o `api-write` do grupo
| (30/min por token). O 429 sai no envelope `rate_limited` da API, nao no
| JSON `{message}` que a tela consome.
*/

const BADGE_REFRESH_API_DIR = 'framework/testing/badgeforge-refresh-api';

beforeEach(function () {
    config([
        'badgeforge.personal_badges.path' => BADGE_REFRESH_API_DIR,
        'badgeforge.providers.refresh_rate_limit' => 10,
        'badgeforge.providers.refresh_rate_decay_seconds' => 60,
    ]);

    File::deleteDirectory(storage_path(BADGE_REFRESH_API_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(BADGE_REFRESH_API_DIR));
});

/**
 * @param  array<string, mixed>  $abilities
 */
function badgeRefreshApiToken(User $user, array $abilities = ['badgeforge:write']): string
{
    return $user->createToken('management-refresh', $abilities)->plainTextToken;
}

function badgeRefreshApiAccount(string $username = 'octocat', bool $paid = true): User
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

function badgeRefreshApiFakeGithub(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/*' => Http::response([
            'stargazers_count' => 1234,
            'forks_count' => 56,
            'open_issues_count' => 7,
            'license' => ['spdx_id' => 'MIT'],
        ]),
    ]);
}

it('regenera a badge e devolve o envelope com url, pngUrl e generatedAt', function () {
    badgeRefreshApiFakeGithub();

    $user = badgeRefreshApiAccount();
    $asset = BadgeAsset::factory()->for($user)->provider()->create();

    $response = $this->withToken(badgeRefreshApiToken($user))
        ->postJson(route('api.v1.badgeforge.badges.refresh', ['badgeAsset' => $asset->id]))
        ->assertOk()
        ->assertJsonStructure(['data' => ['url', 'pngUrl', 'generatedAt']]);

    expect($response->json('data.url'))->toBe(route('badges.personal', [
        'username' => 'octocat', 'badgeAsset' => $asset->id, 'ext' => 'svg',
    ]))
        ->and($response->json('data.pngUrl'))->toEndWith('.png')
        ->and($response->json('data.generatedAt'))->not->toBeNull()
        ->and($asset->refresh()->svg_path)->toBe('personal/octocat/'.$asset->id.'.svg')
        ->and($asset->expires_at?->isFuture())->toBeTrue()
        ->and(app(BadgeAssetStorage::class)->read($asset->svg_path))->toContain('>1.2k<');
});

it('recusa refresh de badge estática no envelope', function () {
    $user = badgeRefreshApiAccount();
    $static = BadgeAsset::factory()->for($user)->static()->create();

    $this->withToken(badgeRefreshApiToken($user))
        ->postJson(route('api.v1.badgeforge.badges.refresh', ['badgeAsset' => $static->id]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.errors.badge.0', __('validation.badge_asset.not_provider'));
});

it('recusa refresh para conta Free no envelope', function () {
    $free = badgeRefreshApiAccount('freebie', paid: false);
    $asset = BadgeAsset::factory()->for($free)->provider()->create();

    $this->withToken(badgeRefreshApiToken($free))
        ->postJson(route('api.v1.badgeforge.badges.refresh', ['badgeAsset' => $asset->id]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.errors.badge.0', __('validation.badge_asset.paid_only'));

    expect($asset->refresh()->svg_path)->toBeNull();
});

it('responde 404 para badge de outra conta', function () {
    $owner = badgeRefreshApiAccount('owner');
    $intruder = badgeRefreshApiAccount('intruder');
    $asset = BadgeAsset::factory()->for($owner)->provider()->create();

    $this->withToken(badgeRefreshApiToken($intruder))
        ->postJson(route('api.v1.badgeforge.badges.refresh', ['badgeAsset' => $asset->id]))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    expect($asset->refresh()->svg_path)->toBeNull();
});

it('recusa refresh com token só de leitura', function () {
    $user = badgeRefreshApiAccount();
    $asset = BadgeAsset::factory()->for($user)->provider()->create();

    $this->withToken(badgeRefreshApiToken($user, ['badgeforge:read']))
        ->postJson(route('api.v1.badgeforge.badges.refresh', ['badgeAsset' => $asset->id]))
        ->assertStatus(403);

    expect($asset->refresh()->svg_path)->toBeNull();
});

it('responde 429 no envelope e divide o limite da conta entre tokens', function () {
    badgeRefreshApiFakeGithub();

    $user = badgeRefreshApiAccount();
    $asset = BadgeAsset::factory()->for($user)->provider()->create();

    $first = badgeRefreshApiToken($user);
    $second = badgeRefreshApiToken($user);

    $url = route('api.v1.badgeforge.badges.refresh', ['badgeAsset' => $asset->id]);

    for ($i = 0; $i < 10; $i++) {
        $this->withToken($first)->postJson($url)->assertOk();
    }

    // Outro token da mesma conta: o limite e por conta, nao por token.
    $response = $this->withToken($second)
        ->postJson($url)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');

    expect($response->headers->get('Retry-After'))->not->toBeNull()
        ->and($response->headers->get('X-RateLimit-Limit'))->toBe('10');
});
