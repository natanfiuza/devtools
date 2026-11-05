<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Resultado de `EndpointMetrics`: payload projetado, estado do cache e TTL
 * que deve ir no `Cache-Control` (F5, ADR-083).
 */
final readonly class EndpointMetricsResult
{
    public const string HIT = 'HIT';

    public const string MISS = 'MISS';

    public const string STALE = 'STALE';

    public function __construct(
        public ?EndpointPayload $payload,
        public string $status,
        public int $ttl,
    ) {}
}
