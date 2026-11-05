<?php

namespace App\Support\BadgeForge;

/**
 * Fonte embutida escolhida por `?font=` (ADR-077).
 *
 * `stack` e o valor que vai no atributo `font-family` do SVG (a pilha com o
 * fallback DejaVu); o slug e tambem a chave de `families.<slug>` em
 * font-metrics.json.
 */
final readonly class BadgeFont
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $stack,
    ) {}
}
