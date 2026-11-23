<?php

namespace App\Actions\Colors;

use App\Models\LayoutSystem;
use App\Support\Colors\LayoutSystemSlug;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Restaura o sistema arquivado (F4, ADR-164/ADR-171).
 *
 * Restaurar nao consome vaga. Sistema publico recupera o slug reservado (ou
 * ganha outro se outra conta tomou o valor); sistema privado mantem o valor em
 * `archived_slug` ate a proxima publicacao (ADR-171).
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
                    $public = $system->isPublic();

                    $slug = $public
                        ? $this->slug->claimOrGenerate(
                            $system->archived_slug,
                            $system->name,
                            $system->id,
                        )
                        : null;

                    $system->forceFill([
                        'archived_at' => null,
                        'archived_slug' => $public ? null : $system->archived_slug,
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
