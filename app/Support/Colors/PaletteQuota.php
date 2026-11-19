<?php

namespace App\Support\Colors;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Cota de itens Colors da conta (F4, ADR-096/ADR-123; F4 do LH, ADR-170).
 *
 * Free tem teto configuravel, pago e ilimitado (`null`). A cota e conjunta:
 * paletas e sistemas de layout dividem o mesmo contador. Arquivados contam:
 * so a exclusao definitiva libera uma vaga. A checagem acontece sob lock da
 * linha do usuario em `CreatePalette`/`DuplicatePalette` e nas actions
 * equivalentes do Layout Harmonizer.
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
     * Total usado, incluindo paletas e sistemas arquivados.
     *
     * A tabela de sistemas pode ainda nao existir na janela entre o deploy do
     * codigo e o `migrate`; nesse intervalo ela nao soma (e nunca quebra as
     * telas antigas de paleta).
     */
    public function used(User $user): int
    {
        $used = $user->colorPalettes()->count();

        if (Schema::hasTable('layout_systems')) {
            $used += $user->layoutSystems()->count();
        }

        return $used;
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
