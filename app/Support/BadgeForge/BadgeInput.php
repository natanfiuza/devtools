<?php

namespace App\Support\BadgeForge;

/**
 * Regras de entrada compartilhadas entre a URL publica (`BadgeRequest`) e os
 * presets da conta (`PresetSpecNormalizer`).
 *
 * A validacao e tolerante: valor invalido cai no default, nunca lanca. Isso
 * mantem o contrato do endpoint publico e deixa o preset com o mesmo
 * comportamento do badge renderizado.
 */
final class BadgeInput
{
    public static function integer(?string $raw, int $default, int $min, int $max): int
    {
        $value = $raw !== null && preg_match('/^\d{1,9}$/', trim($raw)) === 1
            ? (int) trim($raw)
            : $default;

        return max($min, min($max, $value));
    }

    public static function link(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);
        $maxLength = (int) config('badgeforge.limits.link_max_length', 2048);

        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            return null;
        }

        if (preg_match('~^https?://[^\s"\'<>]+$~i', $value) !== 1) {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $value : null;
    }
}
