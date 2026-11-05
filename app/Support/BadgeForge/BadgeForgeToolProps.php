<?php

namespace App\Support\BadgeForge;

use App\Models\BadgeAsset;
use App\Models\BadgeCustomLogo;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeCustomProviderDefinition;
use App\Models\BadgeEndpoint;
use App\Models\BadgePreset;
use App\Models\User;
use App\Support\BadgeForge\Providers\ProviderCatalog;
use Illuminate\Database\QueryException;

/**
 * Props da pagina `/tools/badgeforge` (ADR-043, ADR-079).
 *
 * O cliente gera o badge 100% no navegador (ADR-075): geometria, metricas e
 * catalogo de cores vem dos JSONs compartilhados importados no TypeScript.
 * Aqui viajam so o que ele nao consegue importar - endpoint, registro de logos,
 * limites/defaults e, para contas verificadas, os metadados dos logos e
 * presets. Os paths de um logo personalizado nunca viajam aqui: sao buscados
 * sob demanda em `badgeforge.logos.show`. A fixture
 * `tests/Fixtures/badgeforge/tool-props.json` prende o contrato do convidado.
 */
final class BadgeForgeToolProps
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?User $user = null): array
    {
        $canManage = $user instanceof User
            && $user->hasVerifiedEmail()
            && ! $user->isSuspended();

        $isPaid = $user instanceof User && ! $user->effectivePlan()->isFree();

        $customLogos = $canManage ? self::customLogos($user) : [];
        $presets = $canManage ? self::presets($user) : [];
        $personalBadges = self::personalBadges($user);
        $endpointBadges = self::endpointBadges($user);
        $customProviders = $canManage ? self::customProviders($user) : [];
        $personalLimit = $isPaid ? null : 0;

        return [
            'endpoint' => (string) config('badgeforge.static_endpoint', '/api/badges/static'),
            'providerEndpoint' => (string) config('badgeforge.provider_endpoint', '/api/badges'),
            'personalEndpoint' => (string) config('badgeforge.personal_endpoint', '/b'),
            'customEndpoint' => (string) config('badgeforge.custom_providers.path', '/api/badges/custom'),
            'styles' => app(StyleCatalog::class)->ids(),
            'logos' => self::logosByName(),
            'fonts' => app(BadgeFontCatalog::class)->metadata(),
            'providers' => app(ProviderCatalog::class)->forDocs(),
            'limits' => [
                'maxTextLength' => (int) config('badgeforge.limits.max_text_length', 100),
                'logoSize' => [
                    'min' => (int) config('badgeforge.limits.logo_size.min', 8),
                    'max' => (int) config('badgeforge.limits.logo_size.max', 20),
                ],
                'cacheSeconds' => [
                    'default' => (int) config('badgeforge.limits.cache_seconds.default', 86400),
                    'min' => (int) config('badgeforge.limits.cache_seconds.min', 300),
                    'max' => (int) config('badgeforge.limits.cache_seconds.max', 86400),
                ],
                'linkMaxLength' => (int) config('badgeforge.limits.link_max_length', 2048),
                'png' => [
                    'maxWidth' => (int) config('badgeforge.png.max_width', 600),
                ],
                'customLogos' => [
                    'limit' => $canManage
                        ? self::nullableLimit($isPaid
                            ? config('badgeforge.custom_logos.paid_limit')
                            : config('badgeforge.custom_logos.free_limit', 2))
                        : 0,
                    'used' => count($customLogos),
                    'maxBytes' => (int) config('badgeforge.custom_logos.max_bytes', 262144),
                    'maxPaths' => (int) config('badgeforge.custom_logos.max_paths', 200),
                    'maxPathLength' => (int) config('badgeforge.custom_logos.max_path_length', 4000),
                    'maxTotalPathLength' => (int) config('badgeforge.custom_logos.max_total_path_length', 100000),
                    'maxCacheSeconds' => (int) config('badgeforge.custom_logos.max_cache_seconds', 3600),
                    'nameMaxLength' => (int) config('badgeforge.custom_logos.name_max_length', 80),
                ],
                'presets' => [
                    'limit' => $canManage
                        ? self::nullableLimit(config('badgeforge.presets.limit'))
                        : 0,
                    'used' => count($presets),
                    'nameMaxLength' => (int) config('badgeforge.presets.name_max_length', 80),
                ],
                // `null` = ilimitado para pago; `0` = criacao bloqueada
                // (free ou convidado), mesmo com badges antigas na lista.
                'personalBadges' => [
                    'limit' => $personalLimit,
                    'used' => count($personalBadges),
                    'nameMaxLength' => (int) config('badgeforge.personal_badges.name_max_length', 80),
                ],
                // Endpoints JSON (F5, ADR-083): sem limite de quantidade -
                // "apenas cadastrados" e a regra, e todo Free pode cadastrar.
                'endpointBadges' => [
                    'nameMaxLength' => (int) config('badgeforge.endpoint.name_max_length', 80),
                    'urlMaxLength' => (int) config('badgeforge.endpoint.url_max_length', 2048),
                ],
                'providers' => [
                    'maxParamLength' => (int) config('badgeforge.providers.max_param_length', 200),
                ],
                // Providers customizados (F7, ADR-087): criar/editar e so no
                // pago, entao o Free recebe `0` e o convidado nao gerencia.
                'customProviders' => [
                    'limit' => $canManage
                        ? self::nullableLimit($isPaid
                            ? config('badgeforge.custom_providers.limit')
                            : 0)
                        : 0,
                    'used' => count($customProviders),
                    'nameMaxLength' => (int) config('badgeforge.custom_providers.name_max_length', 80),
                    'urlMaxLength' => (int) config('badgeforge.custom_providers.url_max_length', 2048),
                    'examplePathMaxLength' => (int) config('badgeforge.custom_providers.example_path_max_length', 512),
                    'slugMaxLength' => (int) config('badgeforge.custom_providers.slug_max_length', 32),
                    'labelMaxLength' => (int) config('badgeforge.custom_providers.label_max_length', 80),
                    'pointerMaxLength' => (int) config('badgeforge.custom_providers.pointer_max_length', 256),
                    'maxMetrics' => (int) config('badgeforge.custom_providers.max_metrics', 10),
                    'maxPathParams' => (int) config('badgeforge.custom_providers.max_path_params', 5),
                    'paramMaxLength' => (int) config('badgeforge.custom_providers.param_max_length', 200),
                ],
            ],
            'defaults' => [
                'style' => (string) config('badgeforge.default_style', 'flat'),
                'color' => (string) config('badgeforge.colors.default'),
                'labelColor' => (string) config('badgeforge.colors.label'),
                'logoColor' => (string) config('badgeforge.colors.logo'),
                'logoPosition' => (string) config('badgeforge.logo_position', 'left'),
            ],
            'management' => [
                'canManage' => $canManage,
                'canSavePresets' => $canManage,
                'canCreatePersonalBadges' => $canManage && $isPaid,
                // "Apenas cadastrados" e a regra do endpoint: Free cadastra.
                'canCreateEndpointBadges' => $canManage,
                'canCreateCustomProviders' => $canManage && $isPaid,
                'isPaid' => $isPaid,
            ],
            'customLogos' => $customLogos,
            'presets' => $presets,
            'personalBadges' => $personalBadges,
            'endpointBadges' => $endpointBadges,
            'customProviders' => $customProviders,
        ];
    }

    /**
     * Badges pessoais do dono, inclusive apos downgrade (F4, ADR-081): a lista
     * continua visivel para copiar a URL e excluir. A consulta nunca seleciona
     * `credential`/`credential_fingerprint`, e uma migracao ainda nao aplicada
     * devolve lista vazia em vez de derrubar a pagina durante o deploy.
     *
     * @return list<array<string, mixed>>
     */
    private static function personalBadges(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        try {
            $assets = $user->badgeAssets()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get([
                    'id', 'name', 'type', 'provider_slug', 'definition_slug', 'params',
                    'private', 'credential_username', 'credential_set_at',
                    'last_generated_at', 'expires_at', 'created_at',
                ]);
        } catch (QueryException) {
            return [];
        }

        $username = $user->username;
        $base = rtrim((string) config('badgeforge.personal_endpoint', '/b'), '/');

        $badges = $assets->map(static function (BadgeAsset $asset) use ($username, $base): array {
            $id = (string) $asset->id;
            $publicUrl = is_string($username) && $username !== ''
                ? url($base.'/'.$username.'/'.$id)
                : null;

            return [
                'id' => $id,
                'name' => (string) $asset->name,
                'type' => (string) $asset->type,
                'providerSlug' => $asset->provider_slug,
                'definitionSlug' => $asset->definition_slug,
                'params' => $asset->params,
                'private' => (bool) $asset->private,
                'credentialSet' => $asset->credentialSet(),
                'credentialUsername' => $asset->credential_username,
                'url' => $publicUrl === null ? null : $publicUrl.'.svg',
                'pngUrl' => $publicUrl === null ? null : $publicUrl.'.png',
                'lastGeneratedAt' => $asset->last_generated_at?->toIso8601String(),
                'expiresAt' => $asset->expires_at?->toIso8601String(),
                'createdAt' => $asset->created_at?->toIso8601String(),
            ];
        })->all();

        return array_values($badges);
    }

    /**
     * Endpoints JSON do dono (F5, ADR-083): so nome e URLs publicas. A URL de
     * origem nunca selecionada aqui - e segredo de operacao e nao tem uso na
     * tela. A lista sobrevive a downgrade, porque serve e excluir continua
     * valendo; uma migracao ainda nao aplicada devolve lista vazia.
     *
     * @return list<array<string, mixed>>
     */
    private static function endpointBadges(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        try {
            $endpoints = $user->badgeEndpoints()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'name', 'created_at']);
        } catch (QueryException) {
            return [];
        }

        $badges = $endpoints->map(static fn (BadgeEndpoint $endpoint): array => [
            'id' => (string) $endpoint->id,
            'name' => (string) $endpoint->name,
            'svgUrl' => $endpoint->publicSvgUrl(),
            'pngUrl' => $endpoint->publicPngUrl(),
            'createdAt' => $endpoint->created_at?->toIso8601String(),
        ])->all();

        return array_values($badges);
    }

    /**
     * Providers customizados do dono (F7, ADR-087): identidade, coordenadas e
     * metricas, sem `url_template` - a origem e segredo de operacao e nunca
     * entra em prop. O pointer viaja porque e o dono quem o edita; o builder so
     * precisa de id, definicoes e params. Sobrevive ao downgrade, porque servir
     * e excluir continuam valendo. Uma migracao ainda nao aplicada devolve
     * lista vazia em vez de derrubar a pagina.
     *
     * @return list<array<string, mixed>>
     */
    private static function customProviders(User $user): array
    {
        try {
            $providers = $user->badgeCustomProviders()
                ->with(['definitions' => static fn ($query) => $query->get([
                    'id', 'provider_id', 'slug', 'label', 'pointer', 'formatter', 'sort',
                ])])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'name', 'path_params', 'example_path', 'enabled', 'created_at']);
        } catch (QueryException) {
            return [];
        }

        return array_values($providers->map(static fn (BadgeCustomProvider $provider): array => [
            'id' => (string) $provider->getKey(),
            'name' => (string) $provider->name,
            'enabled' => (bool) $provider->enabled,
            'examplePath' => (string) $provider->example_path,
            'pathParams' => array_map(
                static fn (mixed $name): string => (string) $name,
                (array) $provider->path_params,
            ),
            'definitions' => $provider->definitions->map(
                static fn (BadgeCustomProviderDefinition $definition): array => [
                    'slug' => (string) $definition->slug,
                    'label' => (string) $definition->label,
                    'pointer' => (string) $definition->pointer,
                    'formatter' => $definition->formatter->value,
                ],
            )->values()->all(),
        ])->all());
    }

    /**
     * Metadados apenas: os paths ficam no servidor ate a selecao do logo, para
     * que o plano pago (ilimitado) nao infle o payload da pagina.
     *
     * @return list<array{id: string, slug: string, name: string, viewBox: string, pathCount: int, sizeBytes: int}>
     */
    private static function customLogos(User $user): array
    {
        $logos = $user->badgeCustomLogos()
            ->get(['id', 'name', 'view_box', 'path_count', 'size_bytes'])
            ->map(static fn (BadgeCustomLogo $logo): array => [
                'id' => (string) $logo->id,
                'slug' => $logo->slug(),
                'name' => (string) $logo->name,
                'viewBox' => (string) $logo->view_box,
                'pathCount' => (int) $logo->path_count,
                'sizeBytes' => (int) $logo->size_bytes,
            ])
            ->all();

        // Ordem independente da collation do banco (SQLite dos testes difere
        // do MySQL de producao): nome sem caixa, id desempatando.
        usort($logos, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name'])
            ?: strcmp($a['id'], $b['id']));

        return $logos;
    }

    /**
     * O preset guarda so o snapshot do formulario; o logo orfao (custom
     * excluido ou embutido removido da config) e calculado na leitura.
     *
     * @return list<array{id: string, name: string, form: array<string, mixed>, logoOrphaned: bool}>
     */
    private static function presets(User $user): array
    {
        $customSlugs = $user->badgeCustomLogos()->pluck('id')
            ->map(static fn (mixed $id): string => 'u_'.(string) $id)
            ->all();

        $embeddedSlugs = app(LogoCatalog::class)->slugs();

        $presets = $user->badgePresets()
            ->get(['id', 'name', 'payload'])
            ->map(static function (BadgePreset $preset) use ($customSlugs, $embeddedSlugs): array {
                $form = self::formPayload($preset->payload);
                $logo = $form['logo'] ?? null;

                $orphaned = is_string($logo)
                    && $logo !== ''
                    && $logo !== 'none'
                    && ! in_array($logo, $embeddedSlugs, true)
                    && ! in_array($logo, $customSlugs, true);

                return [
                    'id' => (string) $preset->id,
                    'name' => (string) $preset->name,
                    'form' => $form,
                    'logoOrphaned' => $orphaned,
                ];
            })
            ->all();

        usort($presets, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name'])
            ?: strcmp($a['id'], $b['id']));

        return $presets;
    }

    /**
     * O cast `array` do model deveria garantir um array, mas o payload vem do
     * banco: uma linha corrompida cai em formulario vazio em vez de estourar.
     *
     * @return array<string, mixed>
     */
    private static function formPayload(mixed $payload): array
    {
        return is_array($payload) ? $payload : [];
    }

    /**
     * `null` no config significa ilimitado; qualquer outro valor vira inteiro.
     */
    private static function nullableLimit(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * Logos em ordem alfabetica pelo nome exibido, sem diferenciar maiusculas.
     * O slug desempata para a ordem ser estavel. O catalogo continua lendo a
     * config na ordem declarada; a ordenacao e do payload da tela.
     *
     * @return list<array{slug: string, name: string}>
     */
    private static function logosByName(): array
    {
        $logos = app(LogoCatalog::class)->metadata();

        usort(
            $logos,
            static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name'])
                ?: strcmp($a['slug'], $b['slug']),
        );

        return $logos;
    }
}
