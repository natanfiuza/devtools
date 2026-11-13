<?php

namespace App\Actions\Colors;

use App\Models\ColorHistoryEntry;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Registra uma cor no historico da conta (F5, ADR-127).
 *
 * A cor ja chega normalizada pelo FormRequest. Repetir a mesma cor apenas
 * sobe `updated_at`, mantendo uma linha por cor. A corrida entre duas abas
 * cai no indice unico e repete a operacao.
 */
final class RecordColorHistory
{
    public function __invoke(User $user, string $color): ColorHistoryEntry
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $entry = ColorHistoryEntry::query()->firstOrCreate([
                    'user_id' => $user->getKey(),
                    'color' => $color,
                ]);

                $entry->touch();

                return $entry;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }
}
