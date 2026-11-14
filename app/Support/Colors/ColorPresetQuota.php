<?php

namespace App\Support\Colors;

use App\Models\User;

/**
 * Cota de presets da conta (F5, ADR-093).
 *
 * Presets sao configuracoes pequenas; o padrao e sem limite funcional
 * (`null`). O teto configurado existe para operacao e nunca e fixado no
 * codigo. A checagem acontece sob lock da linha do usuario em
 * `CreateColorPreset`.
 */
final class ColorPresetQuota
{
    public function limit(): ?int
    {
        $value = config('colors.presets.limit');

        return $value === null ? null : max(0, (int) $value);
    }

    public function used(User $user): int
    {
        return $user->colorPresets()->count();
    }

    public function reached(User $user): bool
    {
        $limit = $this->limit();

        return $limit !== null && $this->used($user) >= $limit;
    }

    /**
     * @return array{limit: int|null, used: int}
     */
    public function summary(User $user): array
    {
        return [
            'limit' => $this->limit(),
            'used' => $this->used($user),
        ];
    }
}
