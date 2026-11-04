<?php

namespace App\Support\BadgeForge\Providers;

/**
 * Resultado de `ProviderMetrics`: valor da metrica, estado do cache e o TTL
 * que deve ir no `Cache-Control` (F4, ADR-080).
 */
final readonly class ProviderMetricsResult
{
    public const string HIT = 'HIT';

    public const string MISS = 'MISS';

    public const string STALE = 'STALE';

    public function __construct(
        public int|float|string|null $value,
        public string $status,
        public int $ttl,
    ) {}
}
