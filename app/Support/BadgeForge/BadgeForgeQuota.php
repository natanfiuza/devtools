<?php

namespace App\Support\BadgeForge;

use App\Models\User;

/**
 * Limites de logos e presets da conta (ADR-078/ADR-079).
 *
 * Os numeros vivem em `config/badgeforge.php`; `null` significa ilimitado.
 * O Free tem um teto pequeno de logos, o pago nao tem teto; presets sao
 * ilimitados para qualquer conta verificada.
 */
final class BadgeForgeQuota
{
    public function customLogoLimit(User $user): ?int
    {
        $value = $user->effectivePlan()->isFree()
            ? config('badgeforge.custom_logos.free_limit', 2)
            : config('badgeforge.custom_logos.paid_limit');

        return self::configured($value);
    }

    public function presetLimit(User $user): ?int
    {
        return self::configured(config('badgeforge.presets.limit'));
    }

    public function customLogoLimitReached(User $user): bool
    {
        $limit = $this->customLogoLimit($user);

        return $limit !== null && $user->badgeCustomLogos()->count() >= $limit;
    }

    public function presetLimitReached(User $user): bool
    {
        $limit = $this->presetLimit($user);

        return $limit !== null && $user->badgePresets()->count() >= $limit;
    }

    private static function configured(mixed $value): ?int
    {
        return $value === null ? null : max(0, (int) $value);
    }
}
