<?php

namespace App\Support\BadgeForge\Providers;

use App\Support\BadgeForge\BadgeRequest;

/**
 * Formata a metrica projetada para o texto da metade direita do badge
 * (F4, ADR-080).
 *
 * Numeros usam k/M/B, licenca usa `spdx_id` (ou `unknown`) e versao e texto
 * limpo; o resultado e truncado pelo mesmo limite do badge estatico. HTML
 * nunca e escapado aqui: o renderer e o dono dessa responsabilidade.
 */
final class ProviderMetricFormatter
{
    public function format(mixed $value, string $extractorKey): ?string
    {
        $text = match ($extractorKey) {
            'license' => $this->license($value),
            'version' => $this->text($value),
            default => $this->number($value),
        };

        if ($text === null) {
            return null;
        }

        return BadgeRequest::truncate($text, max(1, (int) config('badgeforge.limits.max_text_length', 100)));
    }

    public function number(mixed $value): ?string
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $number = (float) $value;

        if (! is_finite($number) || $number < 0) {
            return null;
        }

        if ($number < 1000) {
            return (string) (int) $number;
        }

        foreach ([[1_000_000_000, 'B'], [1_000_000, 'M'], [1_000, 'k']] as [$divisor, $suffix]) {
            if ($number < $divisor) {
                continue;
            }

            $scaled = $number / (float) $divisor;

            if ($scaled >= 100) {
                return ((int) floor($scaled)).$suffix;
            }

            $scaled = floor($scaled * 10) / 10;

            return rtrim(rtrim(number_format($scaled, 1, '.', ''), '0'), '.').$suffix;
        }

        return (string) (int) $number;
    }

    public function license(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['spdx_id'] ?? null;
        }

        if (! is_string($value)) {
            return $this->text($this->unknownLicense());
        }

        $value = trim($value);

        if ($value === '' || strcasecmp($value, 'NOASSERTION') === 0) {
            return $this->text($this->unknownLicense());
        }

        return $this->text($value);
    }

    public function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);

        if (! is_string($cleaned)) {
            return null;
        }

        $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned) ?? '');

        return $cleaned === '' ? null : $cleaned;
    }

    public function unknownLicense(): string
    {
        return (string) config('badgeforge.providers.unknown_message', 'unknown');
    }
}
