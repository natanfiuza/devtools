<?php

namespace App\Actions\Colors;

use App\Models\ColorPalette;
use App\Support\Colors\PaletteSlug;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Restaura a paleta arquivada (F4, ADR-119/ADR-124).
 *
 * O slug anterior e reivindicado se ainda estiver livre; caso outra paleta
 * tenha tomado o valor nesse meio-tempo, um novo slug e gerado a partir do
 * nome atual. Paleta que nunca foi publicada volta sem slug.
 */
final class RestorePalette
{
    public function __construct(private readonly PaletteSlug $slug) {}

    public function __invoke(ColorPalette $palette): ColorPalette
    {
        if (! $palette->isArchived()) {
            return $palette;
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($palette): ColorPalette {
                    $slug = null;

                    if ($palette->archived_slug !== null || $palette->isPublic()) {
                        $slug = $this->slug->claimOrGenerate($palette->archived_slug, $palette->name);
                    }

                    $palette->forceFill([
                        'archived_at' => null,
                        'archived_slug' => null,
                        'slug' => $slug,
                    ])->save();

                    return $palette;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }
}
