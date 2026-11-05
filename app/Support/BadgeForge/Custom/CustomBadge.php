<?php

namespace App\Support\BadgeForge\Custom;

use App\Support\BadgeForge\BadgeSpec;

/**
 * Badge pronta de um provider customizado (F7, ADR-087).
 */
final readonly class CustomBadge
{
    public function __construct(
        public BadgeSpec $spec,
        public string $status,
    ) {}
}
