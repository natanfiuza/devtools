<?php

namespace App\Support\BadgeForge;

/**
 * Um path de logo ja validado (F3, ADR-078).
 *
 * `fillRule`/`clipRule` so chegam aqui como `nonzero` ou `evenodd`; a
 * serializacao no SVG e feita pelo renderer, PHP e TypeScript.
 */
final readonly class LogoPath
{
    public function __construct(
        public string $d,
        public ?string $fillRule = null,
        public ?string $clipRule = null,
    ) {}
}
