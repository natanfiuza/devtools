<?php

namespace App\Support\BadgeForge\Providers;

use App\Support\BadgeForge\BadgeSpec;

/**
 * Badge de provider pronta para render: spec cosmetica + estado do cache.
 */
final readonly class ProviderBadge
{
    public function __construct(
        public BadgeSpec $spec,
        public string $status,
    ) {}
}
