<?php

namespace App\Actions\Colors;

use App\Models\LayoutSystem;
use App\Support\Colors\LayoutSystemSlug;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Restaura o sistema arquivado (F4, ADR-164/ADR-171).
 *
 * Restaurar nao consome vaga. O slug reservado volta se continuar livre; se
 * outra conta tomou o valor nesse meio-tempo, um novo slug nasce do nome atual.
 */
final class RestoreLayoutSystem
{
    public function __construct(private readonly LayoutSystemSlug $slug) {}

    public function __invoke(LayoutSystem $system): LayoutSystem
    {
        if (! $system->isArchived()) {
            return $system;
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($system): LayoutSystem {
                    $slug = null;

                    if ($system->archived_slug !== null || $system->isPublic()) {
                        $slug = $this->slug->claimOrGenerate(
                            $system->archived_slug,
                            $system->name,
                            $system->id,
                        );
                    }

                    $system->forceFill([
                        'archived_at' => null,
                        'archived_slug' => null,
                        'slug' => $slug,
                    ])->save();

                    return $system;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }
}
