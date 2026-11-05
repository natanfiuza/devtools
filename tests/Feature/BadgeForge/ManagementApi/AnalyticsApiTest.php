<?php

use App\Enums\BadgeViewSubject;
use App\Models\BadgeAsset;
use App\Models\BadgeView;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
| Analytics do dono pela API de gestao (F9, ADR-090).
|
| Espelha o painel em JSON: Free fica nos 30 dias com totais e contagem por
| imagem; pago escolhe 7/30/90 e ganha serie e quebras. Janela invalida cai
| no padrao, `exportUrl` nao acompanha (CSV e rota web) e so conta o dono.
*/

function analyticsApiAccount(bool $paid = true, string $username = 'octocat'): User
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

function analyticsApiToken(User $user, array $abilities = ['badgeforge:read']): string
{
    return $user->createToken('management-analytics', $abilities)->plainTextToken;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function analyticsApiView(User $user, string $subjectId, array $overrides = []): BadgeView
{
    return BadgeView::factory()->create(array_merge([
        'user_id' => $user->getKey(),
        'subject_type' => BadgeViewSubject::Personal,
        'subject_id' => $subjectId,
        'ip_hash' => str_pad((string) fake()->unique()->numberBetween(1, 999999), 64, '0'),
        'viewed_at' => now(),
    ], $overrides));
}

it('devolve o recorte Free de 30 dias como no painel, sem exportUrl', function () {
    $user = analyticsApiAccount(paid: false);
    $asset = BadgeAsset::factory()->for($user)->create(['name' => 'readme']);

    analyticsApiView($user, (string) $asset->getKey(), ['ip_hash' => str_repeat('a', 64)]);
    analyticsApiView($user, (string) $asset->getKey(), ['ip_hash' => str_repeat('b', 64)]);
    analyticsApiView($user, (string) $asset->getKey(), ['viewed_at' => now()->subDays(40)]);

    $response = $this->withToken(analyticsApiToken($user))
        ->getJson(route('api.v1.badgeforge.analytics', ['window' => 7]))
        ->assertOk()
        ->assertJsonPath('data.isPaid', false)
        ->assertJsonPath('data.window', 30)
        ->assertJsonPath('data.windows', [])
        ->assertJsonPath('data.freeWindowDays', 30)
        ->assertJsonPath('data.totals', ['views' => 2, 'uniques' => 2])
        ->assertJsonPath('data.subjects.0.name', 'readme')
        ->assertJsonPath('data.subjects.0.views', 2)
        ->assertJsonPath('data.days', [])
        ->assertJsonPath('data.referers', [])
        ->assertJsonPath('data.countries', [])
        ->assertJsonPath('data.formats', [])
        ->assertJsonPath('data.cache', null);

    expect(array_key_exists('exportUrl', $response->json('data')))->toBeFalse()
        ->and((string) $response->getContent())->not->toContain('export');
});

it('pago respeita window=7 e cai em 30 quando a janela é inválida', function () {
    $user = analyticsApiAccount();
    $asset = BadgeAsset::factory()->for($user)->create();

    analyticsApiView($user, (string) $asset->getKey(), ['viewed_at' => now()->subDays(5)]);
    analyticsApiView($user, (string) $asset->getKey(), ['viewed_at' => now()->subDays(60)]);

    $token = analyticsApiToken($user);

    $this->withToken($token)
        ->getJson(route('api.v1.badgeforge.analytics', ['window' => 7]))
        ->assertOk()
        ->assertJsonPath('data.isPaid', true)
        ->assertJsonPath('data.window', 7)
        ->assertJsonPath('data.windows', [7, 30, 90])
        ->assertJsonPath('data.totals.views', 1)
        ->assertJsonCount(7, 'data.days');

    $this->withToken($token)
        ->getJson(route('api.v1.badgeforge.analytics', ['window' => 999]))
        ->assertOk()
        ->assertJsonPath('data.window', 30)
        ->assertJsonCount(30, 'data.days');

    $this->withToken($token)
        ->getJson(route('api.v1.badgeforge.analytics'))
        ->assertOk()
        ->assertJsonPath('data.window', 30);
});

it('só conta as visualizações do dono do token', function () {
    $owner = analyticsApiAccount(username: 'owner');
    $other = analyticsApiAccount(username: 'other');

    $ownAsset = BadgeAsset::factory()->for($owner)->create();
    $otherAsset = BadgeAsset::factory()->for($other)->create();

    analyticsApiView($owner, (string) $ownAsset->getKey());
    analyticsApiView($other, (string) $otherAsset->getKey());
    analyticsApiView($other, (string) $otherAsset->getKey());

    $this->withToken(analyticsApiToken($owner))
        ->getJson(route('api.v1.badgeforge.analytics'))
        ->assertOk()
        ->assertJsonPath('data.totals.views', 1)
        ->assertJsonCount(1, 'data.subjects')
        ->assertJsonPath('data.subjects.0.id', (string) $ownAsset->getKey());
});

it('recusa token só de escrita na leitura de analytics', function () {
    $user = analyticsApiAccount();

    $this->withToken(analyticsApiToken($user, ['badgeforge:write']))
        ->getJson(route('api.v1.badgeforge.analytics'))
        ->assertStatus(403);
});

it('responde 503 badge_unavailable quando o bruto do analytics cai', function () {
    $user = analyticsApiAccount();

    Schema::rename('badge_views', 'badge_views_gone');

    try {
        $response = $this->withToken(analyticsApiToken($user))
            ->getJson(route('api.v1.badgeforge.analytics'))
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('error.code', 'badge_unavailable');
    } finally {
        Schema::rename('badge_views_gone', 'badge_views');
    }

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and((string) $response->getContent())->not->toContain('SQLSTATE');
});
