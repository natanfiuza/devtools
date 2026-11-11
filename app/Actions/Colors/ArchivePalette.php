<?php

namespace App\Actions\Colors;

use App\Models\ColorPalette;
use Illuminate\Support\Facades\DB;

/**
 * Arquiva a paleta (F4, ADR-119/ADR-123).
 *
 * A URL publica deixa de existir e o slug e liberado para outra paleta; o
 * valor fica em `archived_slug` para a restauracao reivindicar. A paleta
 * continua contando na cota e fica somente leitura ate ser restaurada.
 */
final class ArchivePalette
{
    public function __invoke(ColorPalette $palette): ColorPalette
    {
        if ($palette->isArchived()) {
            return $palette;
        }

        return DB::transaction(function () use ($palette): ColorPalette {
            $palette->forceFill([
                'archived_slug' => $palette->slug,
                'slug' => null,
                'archived_at' => now(),
            ])->save();

            return $palette;
        });
    }
}
