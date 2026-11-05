<?php

namespace App\Support\BadgeForge;

/**
 * Largura de texto a partir da tabela de avancos compartilhada.
 *
 * A tabela e aproximada (DejaVu Sans para a pilha Verdana e Arial para a
 * pilha Helvetica). A folga de padding e o fallback largo evitam corte;
 * glifos fora da tabela caem em `fallback_units`.
 */
final class FontMetrics
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(private ?string $path = null) {}

    public function width(string $text, string $family, int $fontSize, int $fontWeight, int $letterSpacing): int
    {
        if ($text === '') {
            return 0;
        }

        $data = $this->data();
        $advance = $data['families'][$family] ?? $data['families']['sans'] ?? [];

        if (! is_array($advance)) {
            $advance = [];
        }

        $fallback = (int) ($data['fallback_units'] ?? 650);
        $zeroWidth = $data['zero_width_ranges'] ?? [];

        $units = 0;
        $count = 0;

        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            $codePoint = mb_ord($char, 'UTF-8') ?: 0;
            $count++;

            if (self::isZeroWidth($codePoint, $zeroWidth)) {
                continue;
            }

            $units += (int) ($advance[(string) $codePoint] ?? $fallback);
        }

        // Fontes empacotadas trazem o proprio percentual (Bold/Regular);
        // `sans`/`helvetica` continuam na media global.
        $boldPercent = (int) ($data['family_bold_percent'][$family] ?? $data['bold_percent'] ?? 100);

        if ($fontWeight >= 600 && $boldPercent !== 100) {
            $units = intdiv($units * $boldPercent + 50, 100);
        }

        $pixels = max(1, intdiv($units * $fontSize + 500, 1000));

        if ($letterSpacing > 0) {
            $pixels += $letterSpacing * $count;
        }

        return $pixels;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $ranges
     */
    private static function isZeroWidth(int $codePoint, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($codePoint >= (int) $range[0] && $codePoint <= (int) $range[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $path = $this->path ?? (string) config('badgeforge.font_metrics_path');

        if (! isset(self::$cache[$path])) {
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            self::$cache[$path] = is_array($decoded) ? $decoded : [];
        }

        return $this->data = self::$cache[$path];
    }
}
