<?php

use App\Enums\CustomMetricFormatter;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeProvider;
use App\Support\BadgeForge\Endpoint\HostResolver;
use App\Support\BadgeForge\Providers\ProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Precedencia da rota custom (F7, ADR-087).
|
| `custom/{ulid}/...` precisa vencer `{provider}/{definition}/{path}` e o slug
| `custom` nunca pode virar provider global do catalogo.
*/

it('mantem a rota custom fora da rota generica de provider', function () {
    BadgeProvider::factory()->create(['slug' => 'custom', 'enabled' => true]);
    app()->forgetInstance(ProviderCatalog::class);

    $catalog = app(ProviderCatalog::class);

    expect(array_column($catalog->forDocs(), 'slug'))->not->toContain('custom')
        ->and($catalog->pair('custom', 'stars'))->toBeNull();

    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(
            '{"stargazers_count":42}',
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);

    $provider = BadgeCustomProvider::factory()->create();
    $provider->definitions()->create([
        'slug' => 'stars',
        'label' => 'Stars',
        'pointer' => '/stargazers_count',
        'formatter' => CustomMetricFormatter::Number,
        'sort' => 0,
    ]);

    $response = $this->get(
        '/api/badges/custom/'.$provider->id.'/stars/natanfiuza/devtools.svg',
    )->assertOk();

    expect((string) $response->getContent())->toContain('>42<')
        ->and($response->headers->get('X-Badge-Cache'))->toBe('MISS');
});
