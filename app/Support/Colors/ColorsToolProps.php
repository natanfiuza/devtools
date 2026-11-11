<?php

namespace App\Support\Colors;

use App\Models\User;

/**
 * Props do grupo Colors na pagina generica (ADR-043, ADR-122, ADR-123).
 *
 * O Color Palette Generator importa imagem so no navegador; o servidor entrega
 * apenas os tetos de `config/tools.php` e, para quem esta logado, os limites do
 * formulario de salvar e as tags da conta como sugestao. Nenhum limite mora no
 * cliente, e a importacao nao faz requisicao ao servidor (CLAUDE.md).
 */
final class ColorsToolProps
{
    /**
     * @return array{
     *     image: array{maxBytes: int, maxSide: int, maxPixels: int, analysisSide: int, fetchTimeoutMs: int},
     *     palette: array{
     *         nameMaxLength: int,
     *         descriptionMaxLength: int,
     *         tagsPerPalette: int,
     *         tagNameMaxLength: int,
     *         tags: list<array{id: string, name: string}>
     *     }
     * }
     */
    public static function for(?User $user = null): array
    {
        return [
            'image' => self::imageLimits(),
            'palette' => [
                ...self::formLimits(),
                // O anonimo nao tem tags; o gerador esconde as sugestoes.
                'tags' => array_values(
                    $user?->colorTags()
                        ->orderBy('position')
                        ->get(['id', 'name'])
                        ->map(static fn ($tag): array => ['id' => $tag->id, 'name' => $tag->name])
                        ->all() ?? [],
                ),
            ],
        ];
    }

    /**
     * Tetos da importacao de imagem do gerador/editor (ADR-122).
     *
     * @return array{maxBytes: int, maxSide: int, maxPixels: int, analysisSide: int, fetchTimeoutMs: int}
     */
    public static function imageLimits(): array
    {
        return [
            'maxBytes' => (int) config('tools.limits.colors_image.max_bytes'),
            'maxSide' => (int) config('tools.limits.colors_image.max_side'),
            'maxPixels' => (int) config('tools.limits.colors_image.max_pixels'),
            'analysisSide' => (int) config('tools.limits.colors_image.analysis_side'),
            'fetchTimeoutMs' => (int) config('tools.limits.colors_image.fetch_timeout_ms'),
        ];
    }

    /**
     * Tetos do formulario de paleta, usados pelo modal de salvar e pelo
     * editor. A configuracao continua sendo a unica fonte dos numeros.
     *
     * @return array{nameMaxLength: int, descriptionMaxLength: int, tagsPerPalette: int, tagNameMaxLength: int}
     */
    public static function formLimits(): array
    {
        return [
            'nameMaxLength' => (int) config('colors.palettes.name_max_length'),
            'descriptionMaxLength' => (int) config('colors.palettes.description_max_length'),
            'tagsPerPalette' => (int) config('colors.tags.per_palette'),
            'tagNameMaxLength' => (int) config('colors.tags.name_max_length'),
        ];
    }
}
