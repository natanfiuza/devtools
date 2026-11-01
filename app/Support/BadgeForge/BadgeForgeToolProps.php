<?php

namespace App\Support\BadgeForge;

/**
 * Props da pagina `/tools/badgeforge` (ADR-043).
 *
 * O cliente e 100% offline (ADR-075): geometria, metricas e catalogo de cores
 * vem dos JSONs compartilhados importados no TypeScript. Aqui viajam so o que
 * ele nao consegue importar - o endpoint publico, o registro de logos e os
 * limites/defaults que moram na configuracao. A fixture
 * `tests/Fixtures/badgeforge/tool-props.json` prende os dois lados.
 */
final class BadgeForgeToolProps
{
    /**
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        return [
            'endpoint' => (string) config('badgeforge.endpoint', '/api/badges/static'),
            'styles' => app(StyleCatalog::class)->ids(),
            'logos' => app(LogoCatalog::class)->metadata(),
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
            ],
            'defaults' => [
                'style' => (string) config('badgeforge.default_style', 'flat'),
                'color' => (string) config('badgeforge.colors.default'),
                'labelColor' => (string) config('badgeforge.colors.label'),
                'logoColor' => (string) config('badgeforge.colors.logo'),
                'logoPosition' => (string) config('badgeforge.logo_position', 'left'),
            ],
        ];
    }
}
