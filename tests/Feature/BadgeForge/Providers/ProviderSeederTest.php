<?php

use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderClientRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('semeia 4 providers e 9 definicoes sem parser_class', function () {
    expect(BadgeProvider::query()->count())->toBe(4)
        ->and(BadgeProviderDefinition::query()->count())->toBe(9)
        ->and(BadgeProviderDefinition::query()->whereNotNull('parser_class')->count())->toBe(0)
        ->and(BadgeProvider::query()->orderBy('id')->pluck('slug')->all())
        ->toBe(['github', 'npm', 'packagist', 'docker']);
});

it('mapeia cada definicao para um cliente conhecido', function () {
    $registry = app(ProviderClientRegistry::class);

    foreach (BadgeProviderDefinition::query()->with('provider')->get() as $definition) {
        expect($registry->for($definition)?->key())->toBe($definition->client_key);
    }
});

it('marca suporte a privado conforme a API do provider', function () {
    $private = BadgeProviderDefinition::query()
        ->where('supports_private', true)
        ->orderBy('id')
        ->pluck('slug')
        ->all();

    expect($private)->toBe(['stars', 'forks', 'issues', 'license', 'v', 'pulls']);
});

it('usa o endpoint enxuto de downloads do packagist', function () {
    // O JSON completo do pacote carrega todas as versoes e passa de 7 MB em
    // pacotes grandes - acima de `providers.max_body_bytes`. `stats.json`
    // devolve o mesmo `downloads.total` em poucos KB.
    $definition = BadgeProvider::query()
        ->where('slug', 'packagist')
        ->firstOrFail()
        ->definitions()
        ->where('slug', 'dt')
        ->firstOrFail();

    expect($definition->url_template)
        ->toBe('https://packagist.org/packages/{vendor}/{package}/stats.json');
});

it('e idempotente ao rodar de novo', function () {
    $migration = require database_path('migrations/2026_11_03_000004_seed_badge_providers.php');

    $migration->up();

    expect(BadgeProvider::query()->count())->toBe(4)
        ->and(BadgeProviderDefinition::query()->count())->toBe(9);
});
