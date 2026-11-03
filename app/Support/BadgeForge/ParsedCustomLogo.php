<?php

namespace App\Support\BadgeForge;

/**
 * Resultado da sanitizacao de um SVG de logo personalizado (ADR-078).
 *
 * O que o banco guarda e exatamente isto: viewBox normalizado, paths e o
 * tamanho do upload original. O arquivo em si e descartado.
 */
final readonly class ParsedCustomLogo
{
    /**
     * @param  list<LogoPath>  $paths
     */
    public function __construct(
        public string $viewBox,
        public array $paths,
        public int $sizeBytes,
    ) {}
}
