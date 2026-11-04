<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catalogo inicial de providers do BadgeForge (F4, ADR-080).
 *
 * Fica numa migracao pelo mesmo motivo do catalogo de planos: o deploy roda
 * `migrate --force` e nunca `db:seed`. `insertOrIgnore` roda de novo sem
 * duplicar e sem sobrescrever ajuste administrativo (enabled, cache_ttl,
 * documentation_url). `parser_class` nasce NULL de proposito: o registry
 * resolve `client_key` para uma classe conhecida e nunca executa classe vinda
 * do banco.
 */
return new class extends Migration
{
    /**
     * @var list<array<string, mixed>>
     */
    private const PROVIDERS = [
        [
            'slug' => 'github',
            'name' => 'GitHub',
            'category' => 'vcs',
            'requires_auth' => true,
            'auth_type' => 'bearer',
            'base_url' => 'https://api.github.com',
            'documentation_url' => 'https://docs.github.com/en/rest/repos/repos',
        ],
        [
            'slug' => 'npm',
            'name' => 'npm',
            'category' => 'package',
            'requires_auth' => true,
            'auth_type' => 'bearer',
            'base_url' => 'https://registry.npmjs.org',
            'documentation_url' => 'https://github.com/npm/registry/blob/main/docs/REGISTRY-API.md',
        ],
        [
            'slug' => 'packagist',
            'name' => 'Packagist',
            'category' => 'package',
            'requires_auth' => false,
            'auth_type' => null,
            'base_url' => 'https://packagist.org',
            'documentation_url' => 'https://packagist.org/apidoc',
        ],
        [
            'slug' => 'docker',
            'name' => 'Docker Hub',
            'category' => 'container',
            'requires_auth' => true,
            'auth_type' => 'basic',
            'base_url' => 'https://hub.docker.com',
            'documentation_url' => 'https://docs.docker.com/reference/api/hub/latest/',
        ],
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private const DEFINITIONS = [
        [
            'provider' => 'github',
            'slug' => 'stars',
            'name' => 'GitHub stars',
            'label' => 'stars',
            'client_key' => 'github',
            'extractor_key' => 'stars',
            'path_pattern' => '{owner}/{repo}',
            'required_params' => [
                ['name' => 'owner', 'pattern' => '[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?', 'allowSlash' => false, 'placeholderKey' => 'owner'],
                ['name' => 'repo', 'pattern' => '[A-Za-z0-9._-]{1,100}', 'allowSlash' => false, 'placeholderKey' => 'repo'],
            ],
            'url_template' => 'https://api.github.com/repos/{owner}/{repo}',
            'supports_private' => true,
            'example_path' => 'natanfiuza/devtools',
            'sort' => 0,
        ],
        [
            'provider' => 'github',
            'slug' => 'forks',
            'name' => 'GitHub forks',
            'label' => 'forks',
            'client_key' => 'github',
            'extractor_key' => 'forks',
            'path_pattern' => '{owner}/{repo}',
            'required_params' => [
                ['name' => 'owner', 'pattern' => '[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?', 'allowSlash' => false, 'placeholderKey' => 'owner'],
                ['name' => 'repo', 'pattern' => '[A-Za-z0-9._-]{1,100}', 'allowSlash' => false, 'placeholderKey' => 'repo'],
            ],
            'url_template' => 'https://api.github.com/repos/{owner}/{repo}',
            'supports_private' => true,
            'example_path' => 'natanfiuza/devtools',
            'sort' => 1,
        ],
        [
            'provider' => 'github',
            'slug' => 'issues',
            'name' => 'GitHub issues',
            'label' => 'issues',
            'client_key' => 'github',
            'extractor_key' => 'issues',
            'path_pattern' => '{owner}/{repo}',
            'required_params' => [
                ['name' => 'owner', 'pattern' => '[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?', 'allowSlash' => false, 'placeholderKey' => 'owner'],
                ['name' => 'repo', 'pattern' => '[A-Za-z0-9._-]{1,100}', 'allowSlash' => false, 'placeholderKey' => 'repo'],
            ],
            'url_template' => 'https://api.github.com/repos/{owner}/{repo}',
            'supports_private' => true,
            'example_path' => 'natanfiuza/devtools',
            'sort' => 2,
        ],
        [
            'provider' => 'github',
            'slug' => 'license',
            'name' => 'GitHub license',
            'label' => 'license',
            'client_key' => 'github',
            'extractor_key' => 'license',
            'path_pattern' => '{owner}/{repo}',
            'required_params' => [
                ['name' => 'owner', 'pattern' => '[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?', 'allowSlash' => false, 'placeholderKey' => 'owner'],
                ['name' => 'repo', 'pattern' => '[A-Za-z0-9._-]{1,100}', 'allowSlash' => false, 'placeholderKey' => 'repo'],
            ],
            'url_template' => 'https://api.github.com/repos/{owner}/{repo}',
            'supports_private' => true,
            'example_path' => 'natanfiuza/devtools',
            'sort' => 3,
        ],
        [
            'provider' => 'npm',
            'slug' => 'v',
            'name' => 'npm version',
            'label' => 'npm',
            'client_key' => 'npm',
            'extractor_key' => 'version',
            'path_pattern' => '{package}',
            'required_params' => [
                ['name' => 'package', 'pattern' => '[A-Za-z0-9@][A-Za-z0-9@._/-]{0,213}', 'allowSlash' => true, 'placeholderKey' => 'package'],
            ],
            'url_template' => 'https://registry.npmjs.org/{package}/latest',
            'supports_private' => true,
            'example_path' => 'vue',
            'sort' => 0,
        ],
        [
            'provider' => 'npm',
            'slug' => 'dm',
            'name' => 'npm downloads',
            'label' => 'downloads',
            'client_key' => 'npm',
            'extractor_key' => 'downloads',
            'path_pattern' => '{package}',
            'required_params' => [
                ['name' => 'package', 'pattern' => '[A-Za-z0-9@][A-Za-z0-9@._/-]{0,213}', 'allowSlash' => true, 'placeholderKey' => 'package'],
            ],
            'url_template' => 'https://api.npmjs.org/downloads/point/last-month/{package}',
            'supports_private' => false,
            'example_path' => 'vue',
            'sort' => 1,
        ],
        [
            'provider' => 'packagist',
            'slug' => 'dt',
            'name' => 'Packagist downloads',
            'label' => 'downloads',
            'client_key' => 'packagist',
            'extractor_key' => 'downloads',
            'path_pattern' => '{vendor}/{package}',
            'required_params' => [
                ['name' => 'vendor', 'pattern' => '[A-Za-z0-9][A-Za-z0-9._-]{0,99}', 'allowSlash' => false, 'placeholderKey' => 'vendor'],
                ['name' => 'package', 'pattern' => '[A-Za-z0-9][A-Za-z0-9._-]{0,99}', 'allowSlash' => false, 'placeholderKey' => 'package'],
            ],
            // `stats.json` (~14 KB) em vez de `{vendor}/{package}.json`, que
            // carrega todas as versoes e passa de 7 MB em pacotes grandes -
            // acima de `providers.max_body_bytes`. A metrica `downloads.total`
            // e a mesma e vem no topo da resposta.
            'url_template' => 'https://packagist.org/packages/{vendor}/{package}/stats.json',
            'supports_private' => false,
            'example_path' => 'laravel/framework',
            'sort' => 0,
        ],
        [
            'provider' => 'packagist',
            'slug' => 'v',
            'name' => 'Packagist version',
            'label' => 'version',
            'client_key' => 'packagist',
            'extractor_key' => 'version',
            'path_pattern' => '{vendor}/{package}',
            'required_params' => [
                ['name' => 'vendor', 'pattern' => '[A-Za-z0-9][A-Za-z0-9._-]{0,99}', 'allowSlash' => false, 'placeholderKey' => 'vendor'],
                ['name' => 'package', 'pattern' => '[A-Za-z0-9][A-Za-z0-9._-]{0,99}', 'allowSlash' => false, 'placeholderKey' => 'package'],
            ],
            'url_template' => 'https://repo.packagist.org/p2/{vendor}/{package}.json',
            'supports_private' => false,
            'example_path' => 'laravel/framework',
            'sort' => 1,
        ],
        [
            'provider' => 'docker',
            'slug' => 'pulls',
            'name' => 'Docker Hub pulls',
            'label' => 'pulls',
            'client_key' => 'docker',
            'extractor_key' => 'pulls',
            'path_pattern' => '{namespace}/{repository}',
            'required_params' => [
                ['name' => 'namespace', 'pattern' => '[a-z0-9][a-z0-9._-]{0,99}', 'allowSlash' => false, 'placeholderKey' => 'namespace'],
                ['name' => 'repository', 'pattern' => '[a-z0-9][a-z0-9._-]{0,99}', 'allowSlash' => false, 'placeholderKey' => 'repository'],
            ],
            'url_template' => 'https://hub.docker.com/v2/repositories/{namespace}/{repository}/',
            'supports_private' => true,
            'example_path' => 'library/postgres',
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
            $provider = $definition['provider'];
            $providerId = $providerIds[$provider] ?? null;

            if ($providerId === null) {
                continue;
            }

            unset($definition['provider']);

            $rows[] = [
                ...$definition,
                'provider_id' => $providerId,
                'required_params' => json_encode($definition['required_params'], JSON_THROW_ON_ERROR),
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
     * Remove apenas o catalogo que esta migracao semeou; definicoes e
     * providers criados pelo admin com outros slugs ficam intactos.
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
