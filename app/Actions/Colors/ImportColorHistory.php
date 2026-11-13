<?php

namespace App\Actions\Colors;

use App\Models\ColorHistoryEntry;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Importa o historico local para a conta, sob pedido explicito (F5, ADR-132).
 *
 * O lote ja foi validado e normalizado. Cores repetidas sao deduplicadas no
 * proprio lote; para as que ja existem na conta o upsert so toca
 * `updated_at`. Rodar a importacao duas vezes nao duplica nada.
 */
final class ImportColorHistory
{
    /**
     * @param  list<string>  $colors
     * @return int quantidade de cores distintas processadas
     */
    public function __invoke(User $user, array $colors): int
    {
        $unique = array_values(array_unique($colors));

        if ($unique === []) {
            return 0;
        }

        $now = now();

        $rows = array_map(
            static fn (string $color): array => [
                'id' => (string) Str::ulid(),
                'user_id' => $user->getKey(),
                'color' => $color,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $unique,
        );

        ColorHistoryEntry::query()->upsert(
            $rows,
            ['user_id', 'color'],
            ['updated_at'],
        );

        return count($unique);
    }
}
