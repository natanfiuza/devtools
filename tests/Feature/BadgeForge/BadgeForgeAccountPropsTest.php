<?php

use App\Models\BadgeCustomLogo;
use App\Models\BadgePreset;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\BadgeForgeToolProps;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Contrato de conta das props (F3).
|
| O cliente decide o que mostrar por `management`, `limits.customLogos` e
| `limits.presets`. Os paths de logos custom nunca viajam nas props: so
| metadados, porque o plano pago e ilimitado.
*/

function badgeForgePro(User $user): void
{
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);
}

it('descreve a conta Free com o limite de dois logos', function () {
    $user = User::factory()->create();
    $logo = BadgeCustomLogo::factory()->for($user)->create(['name' => 'alpha', 'path_count' => 2]);
    BadgePreset::factory()->for($user)->create(['name' => 'release']);

    $props = BadgeForgeToolProps::for($user);

    expect($props['management'])->toBe([
        'canManage' => true,
        'canSavePresets' => true,
        'isPaid' => false,
    ])
        ->and($props['limits']['customLogos']['limit'])->toBe(2)
        ->and($props['limits']['customLogos']['used'])->toBe(1)
        ->and($props['limits']['presets'])->toBe([
            'limit' => null,
            'used' => 1,
            'nameMaxLength' => 80,
        ])
        ->and($props['customLogos'])->toBe([[
            'id' => $logo->id,
            'slug' => 'u_'.$logo->id,
            'name' => 'alpha',
            'viewBox' => '0 0 24 24',
            'pathCount' => 2,
            'sizeBytes' => 64,
        ]])
        ->and($props['presets'])->toHaveCount(1)
        ->and($props['presets'][0]['id'])->toBe($user->badgePresets()->sole()->id)
        ->and($props['presets'][0]['logoOrphaned'])->toBeFalse();
});

it('nunca expoe os paths dos logos custom nas props', function () {
    $user = User::factory()->create();
    BadgeCustomLogo::factory()->for($user)->create();

    $props = BadgeForgeToolProps::for($user);

    expect($props['customLogos'][0])->not->toHaveKey('paths');
});

it('marca o plano pago como ilimitado', function () {
    $user = User::factory()->create();

    badgeForgePro($user);

    $props = BadgeForgeToolProps::for($user);

    expect($props['management']['isPaid'])->toBe(true)
        ->and($props['limits']['customLogos']['limit'])->toBeNull()
        ->and($props['limits']['presets']['limit'])->toBeNull();
});

it('nao abre a gestao para conta nao verificada ou suspensa', function (string $state) {
    $user = $state === 'suspensa'
        ? User::factory()->create(['suspended_at' => now()])
        : User::factory()->unverified()->create();

    $props = BadgeForgeToolProps::for($user);

    expect($props['management']['canManage'])->toBeFalse()
        ->and($props['limits']['customLogos'])->toBe([
            'limit' => 0,
            'used' => 0,
            'maxBytes' => 262144,
            'maxPaths' => 200,
            'maxPathLength' => 4000,
            'maxTotalPathLength' => 100000,
            'maxCacheSeconds' => 3600,
            'nameMaxLength' => 80,
        ])
        ->and($props['limits']['presets']['limit'])->toBe(0)
        ->and($props['customLogos'])->toBe([])
        ->and($props['presets'])->toBe([]);
})->with([
    'nao verificada' => ['nao verificada'],
    'suspensa' => ['suspensa'],
]);

it('calcula o preset orfao na leitura', function (string $logo, bool $orphaned) {
    $user = User::factory()->create();
    $preset = BadgePreset::factory()->for($user)->create([
        'payload' => ['label' => 'a', 'message' => 'b', 'logo' => $logo],
    ]);

    $props = BadgeForgeToolProps::for($user);
    $entry = collect($props['presets'])->firstWhere('id', $preset->id);

    expect($entry['logoOrphaned'])->toBe($orphaned);
})->with([
    'sem logo' => ['none', false],
    'embutido' => ['github', false],
    'custom inexistente' => ['u_01ARZ3NDEKTSV4RRFFQ69G5FAV', true],
    'desconhecido' => ['nope', true],
]);

it('nao marca como orfao o logo custom que ainda existe', function () {
    $user = User::factory()->create();
    $logo = BadgeCustomLogo::factory()->for($user)->create();
    BadgePreset::factory()->for($user)->create([
        'payload' => ['label' => 'a', 'message' => 'b', 'logo' => 'u_'.$logo->id],
    ]);

    $props = BadgeForgeToolProps::for($user);

    expect($props['presets'][0]['logoOrphaned'])->toBeFalse();
});

it('ordena logos e presets por nome', function () {
    $user = User::factory()->create();

    foreach (['zeta', 'alpha', 'Mid'] as $name) {
        BadgeCustomLogo::factory()->for($user)->create(['name' => $name]);
    }

    $props = BadgeForgeToolProps::for($user);

    expect(array_column($props['customLogos'], 'name'))->toBe(['alpha', 'Mid', 'zeta']);
});

it('nao consulta logos nem presets para convidado', function () {
    $props = BadgeForgeToolProps::for();

    expect($props['management']['canManage'])->toBeFalse()
        ->and($props['customLogos'])->toBe([])
        ->and($props['presets'])->toBe([]);
});
