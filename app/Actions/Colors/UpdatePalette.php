<?php

namespace App\Actions\Colors;

use App\Enums\PaletteVisibility;
use App\Models\ColorPalette;
use App\Support\Colors\PaletteData;
use App\Support\Colors\PaletteOgStorage;
use App\Support\Colors\PaletteSlug;
use App\Support\Colors\PaletteTags;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Atualiza a paleta sem recriar as cores (F4, ADR-123).
 *
 * Publicar pela primeira vez gera o slug; renomear depois nao o altera.
 * Qualquer mudanca visual invalida a imagem OG, que sera regenerada no
 * navegador apos o salvamento bem-sucedido.
 */
final class UpdatePalette
{
    public function __construct(
        private readonly PaletteSlug $slug,
        private readonly PaletteTags $tags,
        private readonly PaletteOgStorage $og,
    ) {}

    public function __invoke(ColorPalette $palette, PaletteData $data): ColorPalette
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($palette, $data): ColorPalette {
                    $palette->loadMissing('tags');

                    $visualChanged = $this->visualChanged($palette, $data);
                    $slug = $palette->slug;

                    if ($data->visibility === PaletteVisibility::Public && $slug === null) {
                        $slug = $this->slug->unique($this->slug->base($data->name));
                    }

                    $palette->forceFill([
                        'name' => $data->name,
                        'description' => $data->description,
                        'visibility' => $data->visibility,
                        'kind' => $data->kind,
                        'algorithm_version' => $data->algorithmVersion,
                        'colors' => $data->colors,
                        'roles' => $data->roles,
                        'source' => $data->source,
                        'colors_count' => count($data->colors['items']),
                        'slug' => $slug,
                    ])->save();

                    $this->tags->sync($palette->user, $palette, $data->tags);

                    if ($visualChanged) {
                        $this->invalidateOg($palette);
                    }

                    return $palette->refresh()->load('tags');
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }

    private function visualChanged(ColorPalette $palette, PaletteData $data): bool
    {
        return $palette->name !== $data->name
            || $palette->description !== $data->description
            || $palette->kind !== $data->kind
            || $palette->algorithm_version !== $data->algorithmVersion
            || $palette->colors !== $data->colors
            || $palette->roles !== $data->roles;
    }

    private function invalidateOg(ColorPalette $palette): void
    {
        if (! $palette->hasOg()) {
            return;
        }

        $this->og->delete($palette->id);
        $palette->forceFill(['og_version' => null])->save();
    }
}
