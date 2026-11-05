<?php

namespace App\Support\BadgeForge;

/**
 * Geometria resolvida de um estilo. Todos os numeros vem de
 * resources/badgeforge/styles.json.
 */
final readonly class Style
{
    /**
     * @param  array<string, list<array{offset: string, color: string, opacity: string}>>  $gradients
     * @param  array{height: int, color: string, opacity: string}|null  $bottomShadow
     */
    public function __construct(
        public string $id,
        public int $height,
        public int $radius,
        public int $padding,
        public int $gap,
        public int $fontSize,
        public int $fontWeight,
        public bool $uppercase,
        public int $letterSpacing,
        public int $baseline,
        public int $logoSize,
        public string $fontFamily,
        public string $metricsFamily,
        public ?string $gradient,
        public ColorValue $textColor,
        public ?ColorValue $labelColor,
        public ?ColorValue $messageColor,
        public ?array $bottomShadow,
        public array $gradients = [],
    ) {}

    /**
     * @return list<array{offset: string, color: string, opacity: string}>
     */
    public function gradientStops(): array
    {
        return $this->gradient !== null ? ($this->gradients[$this->gradient] ?? []) : [];
    }
}
