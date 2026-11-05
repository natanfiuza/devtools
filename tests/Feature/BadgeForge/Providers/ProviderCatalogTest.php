<?php

use App\Models\BadgeProvider;
use App\Support\BadgeForge\Providers\ProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('resolve par provider/definicao habilitado', function () {
    $pair = app(ProviderCatalog::class)->pair('github', 'stars');

    expect($pair?->provider->slug)->toBe('github')
        ->and($pair?->definition->slug)->toBe('stars')
        ->and($pair?->definition->client_key)->toBe('github');
});

it('devolve null para provider ou definicao desconhecida ou desabilitada', function () {
    $catalog = app(ProviderCatalog::class);

    expect($catalog->pair('nope', 'stars'))->toBeNull()
        ->and($catalog->pair('github', 'nope'))->toBeNull();

    BadgeProvider::query()->where('slug', 'npm')->update(['enabled' => false]);
    app()->forgetInstance(ProviderCatalog::class);

    expect(app(ProviderCatalog::class)->pair('npm', 'v'))->toBeNull();
});

it('publica o catalogo das 15 metricas em ordem estavel', function () {
    $docs = app(ProviderCatalog::class)->forDocs();

    expect(array_column($docs, 'slug'))
        ->toBe(['github', 'npm', 'packagist', 'docker', 'codecov', 'coveralls', 'laravel', 'php', 'python'])
        ->and(array_merge(...array_map(
            static fn (array $provider): array => array_column($provider['definitions'], 'slug'),
            $docs,
        )))->toBe([
            'stars', 'forks', 'issues', 'license', 'v', 'dm', 'dt', 'v', 'pulls',
            'coverage', 'coverage', 'v', 'dt', 'v', 'v',
        ]);

    $github = $docs[0];

    expect($github['requiresAuth'])->toBeTrue()
        ->and($github['documentationUrl'])->toContain('github.com')
        ->and($github['definitions'][0]['params'])->toBe([
            [
                'name' => 'owner',
                'required' => true,
                'pattern' => '[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?',
                'allowSlash' => false,
                'placeholderKey' => 'owner',
            ],
            [
                'name' => 'repo',
                'required' => true,
                'pattern' => '[A-Za-z0-9._-]{1,100}',
                'allowSlash' => false,
                'placeholderKey' => 'repo',
            ],
        ]);
});

it('publica definicao sem params com lista vazia e path literal', function () {
    $docs = app(ProviderCatalog::class)->forDocs();
    $php = collect($docs)->firstWhere('slug', 'php');

    expect($php['definitions'][0]['pathPattern'])->toBe('latest')
        ->and($php['definitions'][0]['examplePath'])->toBe('latest')
        ->and($php['definitions'][0]['params'])->toBe([]);
});

it('ignora provider com slug reservado static', function () {
    BadgeProvider::factory()->create(['slug' => 'static', 'enabled' => true]);
    app()->forgetInstance(ProviderCatalog::class);

    $slugs = array_column(app(ProviderCatalog::class)->forDocs(), 'slug');

    expect($slugs)->not->toContain('static');
});

it('tolera tabela ausente devolvendo catalogo vazio', function () {
    Schema::rename('badge_providers', 'badge_providers_missing');
    app()->forgetInstance(ProviderCatalog::class);

    $catalog = app(ProviderCatalog::class);

    expect($catalog->all())->toBe([])
        ->and($catalog->pair('github', 'stars'))->toBeNull()
        ->and($catalog->forDocs())->toBe([]);

    Schema::rename('badge_providers_missing', 'badge_providers');
});
