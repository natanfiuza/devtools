<?php

namespace App\Support\BadgeForge\Providers;

use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;

/**
 * Par provider + definicao resolvido pelo catalogo (F4, ADR-080).
 */
final readonly class ProviderPair
{
    public function __construct(
        public BadgeProvider $provider,
        public BadgeProviderDefinition $definition,
    ) {}
}
