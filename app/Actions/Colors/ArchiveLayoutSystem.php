<?php

namespace App\Actions\Colors;

use App\Models\LayoutSystem;
use Illuminate\Support\Facades\DB;

/**
 * Arquiva o sistema de layout (F4, ADR-164/ADR-171).
 *
 * A URL publica deixa de existir e o slug fica reservado em `archived_slug`;
 * o sistema continua contando na cota conjunta e fica somente leitura ate ser
 * restaurado.
 */
final class ArchiveLayoutSystem
{
    public function __invoke(LayoutSystem $system): LayoutSystem
    {
        if ($system->isArchived()) {
            return $system;
        }

        return DB::transaction(function () use ($system): LayoutSystem {
            $system->forceFill([
                'archived_slug' => $system->slug,
                'slug' => null,
                'archived_at' => now(),
            ])->save();

            return $system;
        });
    }
}
