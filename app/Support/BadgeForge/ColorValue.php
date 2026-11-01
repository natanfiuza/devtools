<?php

namespace App\Support\BadgeForge;

/**
 * Cor resolvida: hexadecimal canonico (#rrggbb) e alfa entre 0 e 1.
 */
final readonly class ColorValue
{
    public function __construct(
        public string $hex,
        public float $alpha = 1.0,
    ) {}

    public function isOpaque(): bool
    {
        return $this->alpha >= 1.0;
    }

    /**
     * Atributos `fill` e, quando translucido, `fill-opacity`.
     */
    public function fillAttributes(): string
    {
        $attributes = 'fill="'.$this->hex.'"';

        if (! $this->isOpaque()) {
            $attributes .= ' fill-opacity="'.Svg::number($this->alpha).'"';
        }

        return $attributes;
    }
}
