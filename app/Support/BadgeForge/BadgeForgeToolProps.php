<?php

namespace App\Support\BadgeForge;

use App\Models\BadgeCustomLogo;
use App\Models\BadgePreset;
use App\Models\User;

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

        return [
            'endpoint' => (string) config('badgeforge.endpoint', '/api/badges/static'),
            'styles' => app(StyleCatalog::class)->ids(),
            'logos' => self::logosByName(),
            'fonts' => app(BadgeFontCatalog::class)->metadata(),
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
                'isPaid' => $isPaid,
            ],
            'customLogos' => $customLogos,
            'presets' => $presets,
        ];
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
