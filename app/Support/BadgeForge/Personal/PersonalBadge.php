<?php

namespace App\Support\BadgeForge\Personal;

use App\Support\BadgeForge\BadgeSpec;

/**
 * Resultado da renderizacao de uma badge pessoal (F4, ADR-081).
 */
final readonly class PersonalBadge
{
    public function __construct(
        public BadgeSpec $spec,
        public string $svg,
        public ?string $png,
        public bool $stale,
    ) {}
}
