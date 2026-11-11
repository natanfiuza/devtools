<?php

namespace App\Support\Colors;

use App\Models\User;

/**
 * Cota de paletas da conta (F4, ADR-096/ADR-123).
 *
 * Free tem teto configuravel, pago e ilimitado (`null`). Arquivadas contam:
 * so a exclusao definitiva libera uma vaga. A checagem acontece sob lock da
 * linha do usuario em `CreatePalette`/`DuplicatePalette`.
 */
final class PaletteQuota
{
    public function limit(User $user): ?int
    {
        $value = $user->effectivePlan()->isFree()
            ? config('colors.palettes.free_limit', 25)
            : config('colors.palettes.paid_limit');

        return $value === null ? null : max(0, (int) $value);
    }

    /**
     * Total usado, incluindo paletas arquivadas.
     */
    public function used(User $user): int
    {
        return $user->colorPalettes()->count();
    }

    public function reached(User $user): bool
    {
        $limit = $this->limit($user);

        return $limit !== null && $this->used($user) >= $limit;
    }

    /**
     * @return array{limit: int|null, used: int, remaining: int|null}
     */
    public function summary(User $user): array
    {
        $limit = $this->limit($user);
        $used = $this->used($user);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
        ];
    }
}
