<?php

use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderClientRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('semeia 9 providers e 15 definicoes sem parser_class', function () {
    expect(BadgeProvider::query()->count())->toBe(9)
        ->and(BadgeProviderDefinition::query()->count())->toBe(15)
        ->and(BadgeProviderDefinition::query()->whereNotNull('parser_class')->count())->toBe(0)
        ->and(BadgeProvider::query()->orderBy('id')->pluck('slug')->all())
        ->toBe(['github', 'npm', 'packagist', 'docker', 'codecov', 'coveralls', 'laravel', 'php', 'python']);
});

it('semeia as categorias de cobertura e runtime', function () {
    expect(BadgeProvider::query()->where('category', 'coverage')->orderBy('id')->pluck('slug')->all())
        ->toBe(['codecov', 'coveralls'])
        ->and(BadgeProvider::query()->where('category', 'runtime')->orderBy('id')->pluck('slug')->all())
        ->toBe(['laravel', 'php', 'python'])
        ->and(BadgeProvider::query()->where('requires_auth', true)->pluck('slug')->all())
        ->toBe(['github', 'npm', 'docker']);
});

it('semeia as definicoes de runtime com path literal sem params', function () {
    $runtimes = BadgeProviderDefinition::query()
        ->whereIn('client_key', ['laravel', 'php', 'python'])
        ->orderBy('provider_id')
        ->orderBy('sort')
        ->get(['path_pattern', 'required_params', 'supports_private']);

    expect($runtimes)->toHaveCount(4);

    foreach ($runtimes as $definition) {
        expect($definition->path_pattern)->toBe('latest')
            ->and($definition->required_params)->toBe([])
            ->and($definition->supports_private)->toBeFalse();
    }
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

    expect(BadgeProvider::query()->count())->toBe(9)
        ->and(BadgeProviderDefinition::query()->count())->toBe(15);
});

it('a migracao da F6 e idempotente e nao sobrescreve ajuste administrativo', function () {
    BadgeProvider::query()->where('slug', 'php')->update(['enabled' => false, 'cache_ttl' => 120]);

    $migration = require database_path('migrations/2026_11_04_000002_seed_badgeforge_f6_providers.php');

    $migration->up();

    $php = BadgeProvider::query()->where('slug', 'php')->sole();

    expect(BadgeProvider::query()->count())->toBe(9)
        ->and(BadgeProviderDefinition::query()->count())->toBe(15)
        ->and($php->enabled)->toBeFalse()
        ->and($php->cache_ttl)->toBe(120);
});
