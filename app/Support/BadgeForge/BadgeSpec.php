<?php

namespace App\Support\BadgeForge;

/**
 * Pedido normalizado, pronto para o renderer. Imutavel e sem dependencia de
 * request/config - e o contrato entre a camada HTTP e o SVG.
 */
final readonly class BadgeSpec
{
    public function __construct(
        public string $label,
        public string $message,
        public Style $style,
        public ?BadgeFont $font,
        public ColorValue $labelColor,
        public ColorValue $messageColor,
        public ?Logo $logo,
        public ColorValue $logoColor,
        public int $logoSize,
        public string $logoPosition,
        public ?string $link,
        public int $cacheSeconds,
    ) {}

    public function logoOnLabel(): bool
    {
        return $this->logo !== null && $this->logoPosition === 'left';
    }

    public function logoOnMessage(): bool
    {
        return $this->logo !== null && $this->logoPosition === 'right';
    }
}
