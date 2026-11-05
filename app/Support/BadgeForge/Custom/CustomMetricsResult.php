<?php

namespace App\Support\BadgeForge\Custom;

/**
 * Resultado da resolucao das metricas customizadas (F7, ADR-087).
 */
final readonly class CustomMetricsResult
{
    public const string HIT = 'HIT';

    public const string MISS = 'MISS';

    public const string STALE = 'STALE';

    /**
     * @param  array<string, string|null>  $metrics
     */
    public function __construct(
        public array $metrics,
        public string $status,
        public int $ttl,
    ) {}
}
