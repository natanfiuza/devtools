<?php

use App\Models\BadgeAsset;
use App\Models\BadgeCustomLogo;
use App\Models\BadgeEndpoint;
use App\Models\BadgePreset;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\BadgeForgeToolProps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

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
        'canCreatePersonalBadges' => false,
        'canCreateEndpointBadges' => true,
        'isPaid' => false,
    ])
        ->and($props['limits']['customLogos']['limit'])->toBe(2)
        ->and($props['limits']['customLogos']['used'])->toBe(1)
        ->and($props['limits']['presets'])->toBe([
            'limit' => null,
            'used' => 1,
            'nameMaxLength' => 80,
        ])
        ->and($props['limits']['personalBadges'])->toBe([
            'limit' => 0,
            'used' => 0,
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
        ->and($props['management']['canCreatePersonalBadges'])->toBeTrue()
        ->and($props['management']['canCreateEndpointBadges'])->toBeTrue()
        ->and($props['limits']['customLogos']['limit'])->toBeNull()
        ->and($props['limits']['presets']['limit'])->toBeNull()
        ->and($props['limits']['personalBadges']['limit'])->toBeNull();
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
        ->and($props['management']['canCreatePersonalBadges'])->toBeFalse()
        ->and($props['management']['canCreateEndpointBadges'])->toBeFalse()
        ->and($props['limits']['personalBadges'])->toBe([
            'limit' => 0,
            'used' => 0,
            'nameMaxLength' => 80,
        ])
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
        ->and($props['presets'])->toBe([])
        ->and($props['personalBadges'])->toBe([])
        ->and($props['endpointBadges'])->toBe([]);
});

it('lista as badges pessoais do dono com URLs publicas e sem credencial', function () {
    $user = User::factory()->create(['username' => 'natanfiuza']);
    $asset = BadgeAsset::factory()->for($user)->provider()->private()->create([
        'name' => 'Estrelas',
        'credential' => 'ghp_segredo',
        'credential_username' => 'natanfiuza',
        'credential_set_at' => now(),
        'params' => ['owner' => 'natanfiuza', 'repo' => 'devtools'],
    ]);

    $props = BadgeForgeToolProps::for($user);
    $entry = $props['personalBadges'][0];

    expect($props['personalBadges'])->toHaveCount(1)
        ->and($entry)->toBe([
            'id' => $asset->id,
            'name' => 'Estrelas',
            'type' => 'provider',
            'providerSlug' => 'github',
            'definitionSlug' => 'stars',
            'params' => ['owner' => 'natanfiuza', 'repo' => 'devtools'],
            'private' => true,
            'credentialSet' => true,
            'credentialUsername' => 'natanfiuza',
            'url' => url('/b/natanfiuza/'.$asset->id.'.svg'),
            'pngUrl' => url('/b/natanfiuza/'.$asset->id.'.png'),
            'lastGeneratedAt' => null,
            'expiresAt' => null,
            'createdAt' => $asset->created_at?->toIso8601String(),
        ])
        ->and($entry)->not->toHaveKeys(['credential', 'credential_fingerprint', 'svg_path', 'png_path'])
        ->and($props['limits']['personalBadges'])->toBe(['limit' => 0, 'used' => 1, 'nameMaxLength' => 80]);
});

it('omite as URLs enquanto a conta nao tem username', function () {
    $user = User::factory()->create(['username' => null]);
    BadgeAsset::factory()->for($user)->static()->create();

    $entry = BadgeForgeToolProps::for($user)['personalBadges'][0];

    expect($entry['url'])->toBeNull()
        ->and($entry['pngUrl'])->toBeNull();
});

it('nao derruba a pagina quando a tabela de badges ainda nao existe', function () {
    $user = User::factory()->create();

    Schema::rename('badge_assets', 'badge_assets_gone');

    try {
        $props = BadgeForgeToolProps::for($user);

        expect($props['personalBadges'])->toBe([])
            ->and($props['limits']['personalBadges'])->toBe(['limit' => 0, 'used' => 0, 'nameMaxLength' => 80]);
    } finally {
        Schema::rename('badge_assets_gone', 'badge_assets');
    }
});

it('lista os endpoints JSON sem a URL de origem', function () {
    $user = User::factory()->create();
    $endpoint = BadgeEndpoint::factory()->for($user)->create([
        'name' => 'deploys',
        'url' => 'https://example.com/badge.json',
    ]);

    $props = BadgeForgeToolProps::for($user);

    expect($props['endpointBadges'])->toHaveCount(1)
        ->and($props['endpointBadges'][0])->toBe([
            'id' => $endpoint->id,
            'name' => 'deploys',
            'svgUrl' => url('/api/badges/endpoint/'.$endpoint->id.'.svg'),
            'pngUrl' => url('/api/badges/endpoint/'.$endpoint->id.'.png'),
            'createdAt' => $endpoint->created_at?->toIso8601String(),
        ])
        ->and($props['endpointBadges'][0])->not->toHaveKey('url')
        ->and($props['limits']['endpointBadges'])->toBe([
            'nameMaxLength' => 80,
            'urlMaxLength' => 2048,
        ]);
});

it('nao derruba a pagina quando a tabela de endpoints ainda nao existe', function () {
    $user = User::factory()->create();

    Schema::rename('badge_endpoints', 'badge_endpoints_gone');

    try {
        $props = BadgeForgeToolProps::for($user);

        expect($props['endpointBadges'])->toBe([]);
    } finally {
        Schema::rename('badge_endpoints_gone', 'badge_endpoints');
    }
});
