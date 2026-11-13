<?php

namespace App\Support\Colors;

use App\Models\ColorPalette;
use App\Models\ColorPaletteFavorite;
use App\Models\ColorTag;

/**
 * Shapes de paleta para Inertia (F4, ADR-123/ADR-124).
 *
 * A lista e propositalmente enxuta (previa + metadados); o editor recebe o
 * payload completo para reabrir sem recalcular; a pagina publica nao expoe
 * papeis, origem nem nada que so diga respeito ao dono.
 */
final class PalettePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function row(ColorPalette $palette, ?int $previewColors = null): array
    {
        $preview = $previewColors ?? (int) config('colors.palettes.preview_colors', 8);

        return [
            'id' => $palette->id,
            'name' => $palette->name,
            'description' => $palette->description,
            'kind' => $palette->kind->value,
            'visibility' => $palette->visibility->value,
            'colors_count' => $palette->colors_count,
            'duplications_count' => $palette->duplications_count,
            'archived' => $palette->isArchived(),
            'slug' => $palette->slug,
            'preview' => $this->preview($palette, $preview),
            'tags' => $this->tags($palette),
            'updated_at' => $palette->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editor(ColorPalette $palette): array
    {
        return [
            'id' => $palette->id,
            'name' => $palette->name,
            'description' => $palette->description,
            'visibility' => $palette->visibility->value,
            'kind' => $palette->kind->value,
            'algorithm_version' => $palette->algorithm_version,
            'colors' => $palette->colors,
            'roles' => $palette->roles,
            'source' => $palette->source,
            'colors_count' => $palette->colors_count,
            'duplications_count' => $palette->duplications_count,
            'archived' => $palette->isArchived(),
            'archived_at' => $palette->archived_at?->toIso8601String(),
            'slug' => $palette->slug,
            'has_og' => $palette->hasOg(),
            'tags' => $this->tags($palette),
            'updated_at' => $palette->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Linha da aba Favoritas: o retrato atual da origem + quando foi salva.
     *
     * @return array<string, mixed>
     */
    public function favoriteRow(ColorPaletteFavorite $favorite): array
    {
        return array_merge($this->row($favorite->palette), [
            'favorited_at' => $favorite->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function public(ColorPalette $palette): array
    {
        return [
            'id' => $palette->id,
            'name' => $palette->name,
            'description' => $palette->description,
            'kind' => $palette->kind->value,
            // So para o comentario do arquivo exportado; os HEX sao os salvos.
            'algorithm_version' => $palette->algorithm_version,
            'colors' => $palette->colors,
            'colors_count' => $palette->colors_count,
            'duplications_count' => $palette->duplications_count,
            'slug' => $palette->slug,
            'tags' => $this->tags($palette),
            'updated_at' => $palette->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<string>
     */
    private function preview(ColorPalette $palette, int $limit): array
    {
        $hexes = array_map(
            static fn (array $item): string => (string) $item['hex'],
            $palette->colors['items'],
        );

        return array_slice($hexes, 0, max(1, $limit));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function tags(ColorPalette $palette): array
    {
        return array_values(
            $palette->tags
                ->map(static fn (ColorTag $tag): array => ['id' => $tag->id, 'name' => $tag->name])
                ->all(),
        );
    }
}
