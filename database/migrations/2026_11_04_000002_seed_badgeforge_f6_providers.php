<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Providers de cobertura e runtime do BadgeForge (F6, ADR-085).
 *
 * Migracao aditiva: o catalogo da F4 fica intacto e esta so acrescenta os
 * cinco providers novos (`insertOrIgnore`), para que o deploy possa rodar o
 * codigo novo antes desta migracao sem perder o catalogo existente.
 *
 * Laravel/PHP/Python nao tem coordenadas: o path publico e o literal `latest`
 * e `required_params` e `[]` (JSON `"[]"`). Laravel expoe duas definicoes
 * (`v` e `dt`) apontando para o mesmo `stats.json`: uma unica requisicao e uma
 * unica entrada de cache projetam as duas metricas.
 *
 * `parser_class` nasce NULL de proposito; `client_key` resolve o cliente em
 * codigo (ADR-080).
 */
return new class extends Migration
{
    /**
     * @var list<array<string, mixed>>
     */
    private const PROVIDERS = [
        [
            'slug' => 'codecov',
            'name' => 'Codecov',
            'category' => 'coverage',
            'requires_auth' => false,
            'auth_type' => null,
            'base_url' => 'https://api.codecov.io',
            'documentation_url' => 'https://docs.codecov.com/reference',
        ],
        [
            'slug' => 'coveralls',
            'name' => 'Coveralls',
            'category' => 'coverage',
            'requires_auth' => false,
            'auth_type' => null,
            'base_url' => 'https://coveralls.io',
            'documentation_url' => 'https://docs.coveralls.io/api-reference',
        ],
        [
            'slug' => 'laravel',
            'name' => 'Laravel',
            'category' => 'runtime',
            'requires_auth' => false,
            'auth_type' => null,
            'base_url' => 'https://packagist.org',
            'documentation_url' => 'https://laravel.com/docs/releases',
        ],
        [
            'slug' => 'php',
            'name' => 'PHP',
            'category' => 'runtime',
            'requires_auth' => false,
            'auth_type' => null,
            'base_url' => 'https://www.php.net',
            'documentation_url' => 'https://www.php.net/releases/',
        ],
        [
            'slug' => 'python',
            'name' => 'Python',
            'category' => 'runtime',
            'requires_auth' => false,
            'auth_type' => null,
            'base_url' => 'https://endoflife.date',
            'documentation_url' => 'https://endoflife.date/python',
        ],
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private const DEFINITIONS = [
        [
            'provider' => 'codecov',
            'slug' => 'coverage',
            'name' => 'Codecov coverage',
            'label' => 'coverage',
            'client_key' => 'codecov',
            'extractor_key' => 'coverage',
            'path_pattern' => '{owner}/{repo}',
            'required_params' => [
                ['name' => 'owner', 'pattern' => '[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?', 'allowSlash' => false, 'placeholderKey' => 'owner'],
                ['name' => 'repo', 'pattern' => '[A-Za-z0-9._-]{1,100}', 'allowSlash' => false, 'placeholderKey' => 'repo'],
            ],
            // Sem `?branch=`: a API v2 usa a branch padrao do repositorio.
            'url_template' => 'https://api.codecov.io/api/v2/github/{owner}/repos/{repo}/',
            'supports_private' => false,
            'example_path' => 'psf/requests',
            'sort' => 0,
        ],
        [
            'provider' => 'coveralls',
            'slug' => 'coverage',
            'name' => 'Coveralls coverage',
            'label' => 'coverage',
            'client_key' => 'coveralls',
            'extractor_key' => 'coverage',
            'path_pattern' => '{owner}/{repo}',
            'required_params' => [
                ['name' => 'owner', 'pattern' => '[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?', 'allowSlash' => false, 'placeholderKey' => 'owner'],
                ['name' => 'repo', 'pattern' => '[A-Za-z0-9._-]{1,100}', 'allowSlash' => false, 'placeholderKey' => 'repo'],
            ],
            // `.json` devolve o ultimo build; sem `?branch=` porque a cobertura
            // publica e a da branch padrao (ADR-085).
            'url_template' => 'https://coveralls.io/github/{owner}/{repo}.json',
            'supports_private' => false,
            'example_path' => 'twbs/bootstrap',
            'sort' => 0,
        ],
        [
            'provider' => 'laravel',
            'slug' => 'v',
            'name' => 'Laravel version',
            'label' => 'laravel',
            'client_key' => 'laravel',
            'extractor_key' => 'version',
            'path_pattern' => 'latest',
            'required_params' => [],
            // `stats.json` (~14 KB) traz `downloads.total` e `versions[]`; o
            // p2 completo passa de 1 MB em pacotes grandes.
            'url_template' => 'https://packagist.org/packages/laravel/framework/stats.json',
            'supports_private' => false,
            'example_path' => 'latest',
            'sort' => 0,
        ],
        [
            'provider' => 'laravel',
            'slug' => 'dt',
            'name' => 'Laravel downloads',
            'label' => 'downloads',
            'client_key' => 'laravel',
            'extractor_key' => 'downloads',
            'path_pattern' => 'latest',
            'required_params' => [],
            'url_template' => 'https://packagist.org/packages/laravel/framework/stats.json',
            'supports_private' => false,
            'example_path' => 'latest',
            'sort' => 1,
        ],
        [
            'provider' => 'php',
            'slug' => 'v',
            'name' => 'PHP version',
            'label' => 'php',
            'client_key' => 'php',
            'extractor_key' => 'version',
            'path_pattern' => 'latest',
            'required_params' => [],
            // Objeto com a chave numerica do major ("8"); `max=1` corta no
            // release mais recente.
            'url_template' => 'https://www.php.net/releases/index.php?json&max=1',
            'supports_private' => false,
            'example_path' => 'latest',
            'sort' => 0,
        ],
        [
            'provider' => 'python',
            'slug' => 'v',
            'name' => 'Python version',
            'label' => 'python',
            'client_key' => 'python',
            'extractor_key' => 'version',
            'path_pattern' => 'latest',
            'required_params' => [],
            // Lista ordenada por ciclo; o primeiro item carrega `latest`.
            'url_template' => 'https://endoflife.date/api/python.json',
            'supports_private' => false,
            'example_path' => 'latest',
            'sort' => 0,
        ],
    ];

    public function up(): void
    {
        $now = now();

        DB::table('badge_providers')->insertOrIgnore(array_map(
            fn (array $provider): array => [
                ...$provider,
                'enabled' => true,
                'cache_ttl' => null,
                'rate_limit_per_minute' => null,
                'config' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::PROVIDERS,
        ));

        $providerIds = DB::table('badge_providers')
            ->whereIn('slug', array_column(self::PROVIDERS, 'slug'))
            ->pluck('id', 'slug');

        $rows = [];

        foreach (self::DEFINITIONS as $definition) {
            $providerId = $providerIds[$definition['provider']] ?? null;

            if ($providerId === null) {
                continue;
            }

            $params = $definition['required_params'];

            unset($definition['provider'], $definition['required_params']);

            $rows[] = [
                ...$definition,
                'provider_id' => $providerId,
                'required_params' => json_encode($params, JSON_THROW_ON_ERROR),
                'optional_params' => null,
                'response_type' => 'json',
                'parser_class' => null,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('badge_provider_definitions')->insertOrIgnore($rows);
    }

    /**
     * Remove apenas o catalogo que esta migracao semeou.
     */
    public function down(): void
    {
        $ids = DB::table('badge_providers')
            ->whereIn('slug', array_column(self::PROVIDERS, 'slug'))
            ->pluck('id');

        DB::table('badge_provider_definitions')->whereIn('provider_id', $ids)->delete();
        DB::table('badge_providers')->whereIn('id', $ids)->delete();
    }
};
