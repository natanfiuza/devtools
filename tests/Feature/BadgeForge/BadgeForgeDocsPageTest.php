<?php

use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
|
| A pagina e publica e indexavel: ela carrega o mesmo catalogo de providers
| que alimenta o builder (nada de credencial nem asset de conta), as quatro
| rotas de imagem, os TTLs de cache, os limites do endpoint JSON e os
| ponteiros da API JSON (F8, ADR-088). O contrato do catalogo e o mesmo da
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
            ->where('staticEndpoint', (string) config('badgeforge.static_endpoint'))
            ->where('providerEndpoint', (string) config('badgeforge.provider_endpoint'))
            ->where('personalEndpoint', (string) config('badgeforge.personal_endpoint'))
            ->where('jsonEndpoint', (string) config('badgeforge.endpoint.path'))
            ->where('customEndpoint', (string) config('badgeforge.custom_providers.path'))
            ->where('cacheTtl', (int) config('badgeforge.providers.cache_ttl'))
            ->where('endpointCacheTtl', (int) config('badgeforge.endpoint.cache_ttl'))
            ->where('endpointRateLimits', [
                'anonymous' => (int) config('badgeforge.endpoint.rate_limit.anonymous'),
                'free' => (int) config('badgeforge.endpoint.rate_limit.free'),
                'paid' => (int) config('badgeforge.endpoint.rate_limit.paid'),
            ])
            ->where('origin', rtrim(url('/'), '/'))
            ->where('jsonApiBaseUrl', '/api/v1/badgeforge')
            ->where('openApiUrl', route('openapi.badges'))
            ->where('tokensSettingsUrl', route('api-tokens.index'))
            ->missing('personalBadges')
            ->missing('credential')
        );
})->with(['en', 'es', 'fr', 'pt-br', 'pt-pt']);

it('lista os nove providers com as quinze metricas', function () {
    $fixture = badgeforgeDocsFixture();

    $definitions = collect($fixture['providers'])
        ->flatMap(fn (array $provider): array => $provider['definitions'])
        ->count();

    expect($fixture['providers'])->toHaveCount(9)
        ->and($definitions)->toBe(15);
});

it('acompanha a configuracao de endpoints e de cache', function () {
    config([
        'badgeforge.static_endpoint' => '/api/badges/static',
        'badgeforge.provider_endpoint' => '/api/badges',
        'badgeforge.personal_endpoint' => '/b',
        'badgeforge.endpoint.path' => '/api/badges/endpoint',
        'badgeforge.custom_providers.path' => '/api/badges/custom',
        'badgeforge.providers.cache_ttl' => 120,
        'badgeforge.endpoint.cache_ttl' => 90,
        'badgeforge.endpoint.rate_limit.anonymous' => 11,
        'badgeforge.endpoint.rate_limit.free' => 22,
        'badgeforge.endpoint.rate_limit.paid' => 33,
    ]);

    $this->get('/'.Locales::fallback().'/docs/badgeforge')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('staticEndpoint', '/api/badges/static')
            ->where('providerEndpoint', '/api/badges')
            ->where('personalEndpoint', '/b')
            ->where('jsonEndpoint', '/api/badges/endpoint')
            ->where('customEndpoint', '/api/badges/custom')
            ->where('cacheTtl', 120)
            ->where('endpointCacheTtl', 90)
            ->where('endpointRateLimits', [
                'anonymous' => 11,
                'free' => 22,
                'paid' => 33,
            ])
            ->where('jsonApiBaseUrl', '/api/v1/badgeforge')
            ->where('openApiUrl', route('openapi.badges'))
            ->where('tokensSettingsUrl', route('api-tokens.index'))
        );
});
