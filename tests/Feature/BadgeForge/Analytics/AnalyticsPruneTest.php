<?php

use App\Models\BadgeView;
use App\Models\BadgeViewDaily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Poda da retencao do analytics (F7, ADR-086): bruto 90 dias, agregado 400.
| A janela conta o dia de hoje e o dry-run nunca apaga nada.
*/

it('remove o bruto e o agregado fora da retencao padrao', function () {
    $user = User::factory()->create();

    $oldView = BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'viewed_at' => now()->subDays(91),
    ]);
    $keptView = BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'viewed_at' => now()->subDays(89),
    ]);

    $oldDaily = BadgeViewDaily::factory()->create([
        'user_id' => $user->getKey(),
        'day' => now()->subDays(401)->toDateString(),
    ]);
    $keptDaily = BadgeViewDaily::factory()->create([
        'user_id' => $user->getKey(),
        'day' => now()->subDays(399)->toDateString(),
    ]);

    $this->artisan('badgeforge:analytics:prune')->assertSuccessful();

    expect(BadgeView::query()->whereKey($oldView->getKey())->exists())->toBeFalse()
        ->and(BadgeView::query()->whereKey($keptView->getKey())->exists())->toBeTrue()
        ->and(BadgeViewDaily::query()->whereKey($oldDaily->getKey())->exists())->toBeFalse()
        ->and(BadgeViewDaily::query()->whereKey($keptDaily->getKey())->exists())->toBeTrue();
});

it('respeita as opcoes de dias informadas', function () {
    $user = User::factory()->create();

    $yesterday = BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'viewed_at' => now()->subDay(),
    ]);
    $today = BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'viewed_at' => now(),
    ]);

    $oldDaily = BadgeViewDaily::factory()->create([
        'user_id' => $user->getKey(),
        'day' => now()->subDays(30)->toDateString(),
    ]);

    $this->artisan('badgeforge:analytics:prune', [
        '--raw-days' => 1,
        '--daily-days' => 10,
    ])->assertSuccessful();

    expect(BadgeView::query()->whereKey($yesterday->getKey())->exists())->toBeFalse()
        ->and(BadgeView::query()->whereKey($today->getKey())->exists())->toBeTrue()
        ->and(BadgeViewDaily::query()->whereKey($oldDaily->getKey())->exists())->toBeFalse();
});

it('respeita a retencao configurada', function () {
    config([
        'badgeforge.analytics.raw_retention_days' => 7,
        'badgeforge.analytics.daily_retention_days' => 14,
    ]);

    $user = User::factory()->create();

    $oldView = BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'viewed_at' => now()->subDays(8),
    ]);
    $oldDaily = BadgeViewDaily::factory()->create([
        'user_id' => $user->getKey(),
        'day' => now()->subDays(15)->toDateString(),
    ]);

    $this->artisan('badgeforge:analytics:prune')->assertSuccessful();

    expect(BadgeView::query()->whereKey($oldView->getKey())->exists())->toBeFalse()
        ->and(BadgeViewDaily::query()->whereKey($oldDaily->getKey())->exists())->toBeFalse();
});

it('nao apaga nada no dry-run', function () {
    $user = User::factory()->create();

    $oldView = BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'viewed_at' => now()->subDays(200),
    ]);
    $oldDaily = BadgeViewDaily::factory()->create([
        'user_id' => $user->getKey(),
        'day' => now()->subDays(500)->toDateString(),
    ]);

    $this->artisan('badgeforge:analytics:prune', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 visualizacao(oes) bruta(s) e 1 linha(s) diaria(s)')
        ->assertSuccessful();

    expect(BadgeView::query()->whereKey($oldView->getKey())->exists())->toBeTrue()
        ->and(BadgeViewDaily::query()->whereKey($oldDaily->getKey())->exists())->toBeTrue();
});
