<?php

use App\Enums\AdminActionType;
use App\Enums\CustomMetricFormatter;
use App\Models\AdminAction;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeCustomProviderDefinition;
use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use App\Models\BadgeView;
use App\Models\BadgeViewDaily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| BadgeForge no painel (F7, ADR-086/ADR-087).
|
| O catalogo global so liga/desliga; o provider customizado e visivel com a
| origem (template e pointer) apenas aqui e so pode ser desabilitado; o volume
| sai do agregado diario, nunca do bruto.
*/

function adminBadgeProvider(string $slug = 'github'): BadgeProvider
{
    return BadgeProvider::query()->where('slug', $slug)->sole();
}

it('fecha as telas do BadgeForge para visitante e para usuario comum', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertForbidden();
})->with([
    'admin.badgeforge.providers.index',
    'admin.badgeforge.custom-providers.index',
    'admin.badgeforge.analytics.index',
]);

it('lista o catalogo com as definicoes de cada provider', function () {
    $admin = User::factory()->admin()->create();
    $provider = adminBadgeProvider();

    $this->actingAs($admin)
        ->get(route('admin.badgeforge.providers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/BadgeProviders')
            ->has('providers', BadgeProvider::query()->count())
            ->where('providers', fn (mixed $providers): bool => collect($providers)->contains(
                fn (array $row): bool => $row['slug'] === $provider->slug
                    && count($row['definitions']) === $provider->definitions()->count()
                    && $row['definitions'][0]['enabled'] === true,
            )));
});

it('desliga um provider e registra a auditoria', function () {
    $admin = User::factory()->admin()->create();
    $provider = adminBadgeProvider();

    $this->actingAs($admin)
        ->patch(route('admin.badgeforge.providers.update', $provider), ['enabled' => false])
        ->assertRedirect(route('admin.badgeforge.providers.index'));

    expect($provider->refresh()->enabled)->toBeFalse();

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::ProviderUpdated)
        ->and($registro->target_type)->toBe(BadgeProvider::class)
        ->and($registro->target_id)->toBe($provider->id)
        ->and($registro->meta)->toMatchArray(['slug' => $provider->slug, 'enabled' => false]);
});

it('desliga uma definicao e nao mexe na de outro provider', function () {
    $admin = User::factory()->admin()->create();
    $provider = adminBadgeProvider();
    $definition = $provider->definitions()->firstOrFail();
    $outro = BadgeProviderDefinition::factory()->create();

    $this->actingAs($admin)
        ->patch(
            route('admin.badgeforge.provider-definitions.update', [$provider, $definition]),
            ['enabled' => false],
        )
        ->assertRedirect(route('admin.badgeforge.providers.index'));

    expect($definition->refresh()->enabled)->toBeFalse();

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::ProviderDefinitionUpdated)
        ->and($registro->meta)->toMatchArray([
            'slug' => $provider->slug,
            'definition' => $definition->slug,
            'enabled' => false,
        ]);

    $this->actingAs($admin)
        ->patch(route('admin.badgeforge.provider-definitions.update', [$provider, $outro]), ['enabled' => false])
        ->assertNotFound();
});

it('nao registra auditoria quando o estado ja e o pedido', function () {
    $admin = User::factory()->admin()->create();
    $provider = adminBadgeProvider();

    $this->actingAs($admin)
        ->patch(route('admin.badgeforge.providers.update', $provider), ['enabled' => true])
        ->assertRedirect(route('admin.badgeforge.providers.index'));

    expect(AdminAction::query()->count())->toBe(0);
});

it('mostra ao admin a origem do provider customizado', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create(['name' => 'Dona API', 'email' => 'owner@example.com']);
    $provider = BadgeCustomProvider::factory()->for($owner)->create([
        'name' => 'api-interna',
        'url_template' => 'https://api.example.com/repos/{owner}/{repo}.json',
        'path_params' => ['owner', 'repo'],
    ]);
    BadgeCustomProviderDefinition::factory()->for($provider, 'provider')->create([
        'slug' => 'stars',
        'pointer' => '/stargazers_count',
        'formatter' => CustomMetricFormatter::Number,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.badgeforge.custom-providers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/BadgeCustomProviders')
            ->has('providers.data', 1)
            ->where('providers.data.0.id', $provider->getKey())
            ->where('providers.data.0.name', 'api-interna')
            ->where('providers.data.0.ownerName', 'Dona API')
            ->where('providers.data.0.ownerEmail', 'owner@example.com')
            ->where('providers.data.0.urlTemplate', 'https://api.example.com/repos/{owner}/{repo}.json')
            ->where('providers.data.0.pathParams', ['owner', 'repo'])
            ->where('providers.data.0.definitions.0.slug', 'stars')
            ->where('providers.data.0.definitions.0.pointer', '/stargazers_count')
            ->where('providers.data.0.definitions.0.formatter', 'number'));
});

it('desabilita o provider customizado, audita sem a origem e derruba a URL publica', function () {
    $admin = User::factory()->admin()->create();
    $provider = BadgeCustomProvider::factory()->create();
    $definition = BadgeCustomProviderDefinition::factory()->for($provider, 'provider')->create([
        'slug' => 'stars',
    ]);

    // O path carrega `/`; monta a URL crua como o navegador faria.
    $url = '/api/badges/custom/'.$provider->getKey().'/'.$definition->slug.'/natanfiuza/devtools.svg';

    // A origem inalcancavel cai no fallback 200; quem derruba a URL e o admin.
    $this->get($url)->assertOk();

    $this->actingAs($admin)
        ->patch(route('admin.badgeforge.custom-providers.update', $provider), ['enabled' => false])
        ->assertRedirect(route('admin.badgeforge.custom-providers.index'));

    expect($provider->refresh()->enabled)->toBeFalse();

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::CustomProviderUpdated)
        ->and($registro->target_type)->toBe(BadgeCustomProvider::class)
        ->and($registro->target_id)->toBe($provider->getKey())
        ->and($registro->meta)->toMatchArray([
            'id' => $provider->getKey(),
            'name' => $provider->name,
            'enabled' => false,
        ])
        ->and($registro->meta)->not->toHaveKey('url_template');
});

it('nao oferece exclusao do provider customizado no painel', function () {
    expect(Route::has('admin.badgeforge.custom-providers.destroy'))->toBeFalse()
        ->and(Route::has('admin.badgeforge.providers.destroy'))->toBeFalse();
});

it('valida a janela do volume contra as janelas do admin', function (mixed $window, int $expected) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.badgeforge.analytics.index', ['window' => $window]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/BadgeAnalytics')
            ->where('analytics.window', $expected)
            ->where('analytics.windows', [7, 30, 90, 400])
            ->has('analytics.days', $expected));
})->with([
    'sete dias' => [7, 7],
    'quarenta dias cai no padrao' => [40, 30],
    'texto cai no padrao' => ['abc', 30],
    'quatrocentos dias' => [400, 400],
]);

it('soma o agregado diario e ignora o bruto', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    BadgeViewDaily::factory()->create([
        'user_id' => $user->getKey(),
        'day' => now()->toDateString(),
        'views' => 6,
        'cache_hits' => 3,
    ]);
    BadgeViewDaily::factory()->create([
        'user_id' => $user->getKey(),
        'day' => now()->toDateString(),
        'views' => 4,
        'cache_hits' => 1,
    ]);
    BadgeViewDaily::factory()->create([
        'user_id' => $user->getKey(),
        'day' => now()->subDays(40)->toDateString(),
        'views' => 10,
        'cache_hits' => 10,
    ]);
    BadgeView::factory()->create(['user_id' => $user->getKey(), 'viewed_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.badgeforge.analytics.index', ['window' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('analytics.totals.views', 10)
            ->where('analytics.totals.cacheHits', 4)
            ->where('analytics.totals.cacheRate', 0.4)
            ->where('analytics.days.6.date', now()->toDateString())
            ->where('analytics.days.6.views', 10)
            ->where('analytics.days.6.cacheHits', 4)
            ->where('analytics.days.5.views', 0));

    $this->actingAs($admin)
        ->get(route('admin.badgeforge.analytics.index', ['window' => 90]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('analytics.totals.views', 20)
            ->where('analytics.totals.cacheHits', 14)
            ->where('analytics.totals.cacheRate', 0.7));
});
