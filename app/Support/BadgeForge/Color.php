<?php

namespace App\Support\BadgeForge;

/**
 * Parser de cor: nomes CSS, aliases de badge, hex 3/4/6/8, rgb(a), hsl(a)
 * e `transparent`.
 *
 * Entrada invalida devolve null; quem chama decide o fallback. O mesmo
 * catalogo (resources/badgeforge/colors.json) alimenta o espelho TypeScript.
 */
final class Color
{
    /** @var array{aliases: array<string, string>, named: array<string, string>}|null */
    private static ?array $catalog = null;

    public static function parse(mixed $raw): ?ColorValue
    {
        if ($raw === null || ! is_string($raw)) {
            return null;
        }

        $value = strtolower(trim($raw));

        if ($value === '') {
            return null;
        }

        if ($value === 'transparent') {
            return new ColorValue('#000000', 0.0);
        }

        $catalog = self::catalog();

        if (isset($catalog['aliases'][$value])) {
            return self::parse($catalog['aliases'][$value]);
        }

        if (isset($catalog['named'][$value])) {
            return self::parse($catalog['named'][$value]);
        }

        if ($value[0] === '#') {
            return self::hex(substr($value, 1));
        }

        // Hex sem "#" (ex.: color=4c1), como nos badges tradicionais.
        if (in_array(strlen($value), [3, 4, 6, 8], true) && ctype_xdigit($value)) {
            return self::hex($value);
        }

        if (str_starts_with($value, 'rgb')) {
            return self::rgb($value);
        }

        if (str_starts_with($value, 'hsl')) {
            return self::hsl($value);
        }

        return null;
    }

    /**
     * Usa `$raw` quando valido e `$fallback` (tambem string) quando nao.
     */
    public static function parseOr(mixed $raw, string $fallback): ColorValue
    {
        return self::parse($raw) ?? self::parse($fallback) ?? new ColorValue('#000000');
    }

    private static function hex(string $hex): ?ColorValue
    {
        if (preg_match('/^[0-9a-f]+$/', $hex) !== 1) {
            return null;
        }

        $length = strlen($hex);

        if (! in_array($length, [3, 4, 6, 8], true)) {
            return null;
        }

        if ($length <= 4) {
            $hex = implode('', array_map(
                static fn (string $char): string => $char.$char,
                str_split($hex),
            ));
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $alpha = strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;

        return new ColorValue(
            sprintf('#%02x%02x%02x', $r, $g, $b),
            self::alpha($alpha),
        );
    }

    private static function rgb(string $value): ?ColorValue
    {
        $pattern = '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([0-9.]+%?)\s*)?\)$/';

        if (preg_match($pattern, $value, $m) !== 1) {
            return null;
        }

        $r = (int) $m[1];
        $g = (int) $m[2];
        $b = (int) $m[3];

        if ($r > 255 || $g > 255 || $b > 255) {
            return null;
        }

        $alpha = isset($m[4]) ? self::alphaValue($m[4]) : 1.0;

        if ($alpha === null) {
            return null;
        }

        return new ColorValue(sprintf('#%02x%02x%02x', $r, $g, $b), self::alpha($alpha));
    }

    private static function hsl(string $value): ?ColorValue
    {
        $pattern = '/^hsla?\(\s*(-?\d{1,4}(?:\.\d+)?)(?:deg)?\s*,\s*(\d{1,3}(?:\.\d+)?)%\s*,\s*(\d{1,3}(?:\.\d+)?)%\s*(?:,\s*([0-9.]+%?)\s*)?\)$/';

        if (preg_match($pattern, $value, $m) !== 1) {
            return null;
        }

        $hue = fmod((float) $m[1], 360.0);
        $hue = $hue < 0 ? $hue + 360.0 : $hue;
        $saturation = (float) $m[2] / 100;
        $lightness = (float) $m[3] / 100;

        if ($saturation > 1 || $lightness > 1) {
            return null;
        }

        $alpha = isset($m[4]) ? self::alphaValue($m[4]) : 1.0;

        if ($alpha === null) {
            return null;
        }

        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $sector = $hue / 60;
        $x = $chroma * (1 - abs(fmod($sector, 2) - 1));

        [$r1, $g1, $b1] = match (true) {
            $sector < 1 => [$chroma, $x, 0.0],
            $sector < 2 => [$x, $chroma, 0.0],
            $sector < 3 => [0.0, $chroma, $x],
            $sector < 4 => [0.0, $x, $chroma],
            $sector < 5 => [$x, 0.0, $chroma],
            default => [$chroma, 0.0, $x],
        };

        $m2 = $lightness - $chroma / 2;

        return new ColorValue(
            sprintf(
                '#%02x%02x%02x',
                (int) round(($r1 + $m2) * 255),
                (int) round(($g1 + $m2) * 255),
                (int) round(($b1 + $m2) * 255),
            ),
            self::alpha($alpha),
        );
    }

    private static function alphaValue(string $value): ?float
    {
        if (str_ends_with($value, '%')) {
            $alpha = (float) substr($value, 0, -1) / 100;
        } else {
            $alpha = (float) $value;
        }

        return $alpha >= 0 && $alpha <= 1 ? $alpha : null;
    }

    private static function alpha(float $value): float
    {
        return round(max(0.0, min(1.0, $value)), 3);
    }

    /**
     * @return array{aliases: array<string, string>, named: array<string, string>}
     */
    private static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        $path = (string) config('badgeforge.colors_path');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        self::$catalog = [
            'aliases' => is_array($data['aliases'] ?? null) ? $data['aliases'] : [],
            'named' => is_array($data['named'] ?? null) ? $data['named'] : [],
        ];

        return self::$catalog;
    }
}
