<?php

namespace App\Support\Colors;

/**
 * Props do grupo Colors na pagina generica (ADR-043, ADR-122).
 *
 * O Color Palette Generator importa imagem so no navegador; o servidor entrega
 * apenas os tetos de `config/tools.php`. Nenhum limite mora no cliente, e a
 * importacao nao faz requisicao ao servidor (CLAUDE.md).
 */
final class ColorsToolProps
{
    /**
     * @return array{image: array{maxBytes: int, maxSide: int, maxPixels: int, analysisSide: int, fetchTimeoutMs: int}}
     */
    public static function for(): array
    {
        return [
            'image' => [
                'maxBytes' => (int) config('tools.limits.colors_image.max_bytes'),
                'maxSide' => (int) config('tools.limits.colors_image.max_side'),
                'maxPixels' => (int) config('tools.limits.colors_image.max_pixels'),
                'analysisSide' => (int) config('tools.limits.colors_image.analysis_side'),
                'fetchTimeoutMs' => (int) config('tools.limits.colors_image.fetch_timeout_ms'),
            ],
        ];
    }
}
