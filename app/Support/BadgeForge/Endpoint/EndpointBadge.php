<?php

namespace App\Support\BadgeForge\Endpoint;

use App\Support\BadgeForge\BadgeSpec;

/**
 * Badge de endpoint pronta para render: spec cosmetica + estado do cache.
 */
final readonly class EndpointBadge
{
    public function __construct(
        public BadgeSpec $spec,
        public string $status,
    ) {}
}
