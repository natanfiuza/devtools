<?php

use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
|
| A pagina e publica e indexavel: ela carrega o mesmo catalogo de providers
| que alimenta o builder (nada de credencial nem asset de conta), os tres
| endpoints configurados e o TTL de cache. O contrato do catalogo e o mesmo da
| fixture exata da tool, entao o teste compara com ela.
*/

function badgeforgeDocsFixture(): array
{
    return json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/badgeforge/tool-props.json')),
        true,
    );
}

it('entrega o catalogo de providers e os endpoints nos cinco idiomas', function (string $locale) {
    $fixture = badgeforgeDocsFixture();

    $this->get('/'.$locale.'/docs/badgeforge')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BadgeForgeDocs')
            ->where('providers', $fixture['providers'])
            ->where('staticEndpoint', (string) config('badgeforge.endpoint'))
            ->where('providerEndpoint', (string) config('badgeforge.provider_endpoint'))
            ->where('personalEndpoint', (string) config('badgeforge.personal_endpoint'))
            ->where('cacheTtl', (int) config('badgeforge.providers.cache_ttl'))
            ->where('origin', rtrim(url('/'), '/'))
            ->missing('personalBadges')
            ->missing('credential')
        );
})->with(['en', 'es', 'fr', 'pt-br', 'pt-pt']);

it('lista os quatro providers com as nove metricas', function () {
    $fixture = badgeforgeDocsFixture();

    $definitions = collect($fixture['providers'])
        ->flatMap(fn (array $provider): array => $provider['definitions'])
        ->count();

    expect($fixture['providers'])->toHaveCount(4)
        ->and($definitions)->toBe(9);
});

it('acompanha a configuracao de endpoints e de cache', function () {
    config([
        'badgeforge.endpoint' => '/api/badges/static',
        'badgeforge.provider_endpoint' => '/api/badges',
        'badgeforge.personal_endpoint' => '/b',
        'badgeforge.providers.cache_ttl' => 120,
    ]);

    $this->get('/'.Locales::fallback().'/docs/badgeforge')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('staticEndpoint', '/api/badges/static')
            ->where('providerEndpoint', '/api/badges')
            ->where('personalEndpoint', '/b')
            ->where('cacheTtl', 120)
        );
});
