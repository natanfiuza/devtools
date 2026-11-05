<?php

namespace App\Support\BadgeForge\Custom;

/**
 * Entrada de cache de metricas customizadas (F7, ADR-087).
 *
 * So os valores ja formatados (por slug) e metadados ficam no store; o corpo
 * cru e a URL de origem nunca sao persistidos.
 */
final readonly class CachedCustomMetrics
{
    /**
     * @param  array<string, string|null>  $metrics
     */
    public function __construct(
        public array $metrics,
        public ?string $etag,
        public int $fetchedAt,
        public int $staleUntil,
        public bool $fallback = false,
    ) {}

    public static function fallback(int $now, int $ttl): self
    {
        return new self([], null, $now, $now + max(1, $ttl), true);
    }

    /**
     * @param  array<string, string|null>  $metrics
     */
    public static function fromMetrics(array $metrics, ?string $etag, int $now, int $staleTtl): self
    {
        return new self($metrics, $etag, $now, $now + max(1, $staleTtl));
    }

    public function isFresh(int $ttl, int $now): bool
    {
        return ! $this->fallback && $this->fetchedAt + max(1, $ttl) > $now;
    }

    public function isStaleUsable(int $now): bool
    {
        return ! $this->fallback && $this->staleUntil > $now;
    }

    public function refresh(int $now, int $staleTtl): self
    {
        return new self($this->metrics, $this->etag, $now, $now + max(1, $staleTtl));
    }

    /**
     * @return array<string, string|null>
     */
    public function metrics(): array
    {
        return $this->fallback ? [] : $this->metrics;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'metrics' => $this->metrics,
            'etag' => $this->etag,
            'fetched_at' => $this->fetchedAt,
            'stale_until' => $this->staleUntil,
            'fallback' => $this->fallback,
        ];
    }

    public static function fromArray(mixed $raw): ?self
    {
        if (! is_array($raw)) {
            return null;
        }

        $metrics = $raw['metrics'] ?? null;
        $etag = $raw['etag'] ?? null;
        $fetchedAt = $raw['fetched_at'] ?? null;
        $staleUntil = $raw['stale_until'] ?? null;
        $fallback = $raw['fallback'] ?? false;

        if (! is_array($metrics) || ! is_int($fetchedAt) || ! is_int($staleUntil) || ! is_bool($fallback)) {
            return null;
        }

        $clean = [];

        foreach ($metrics as $slug => $value) {
            if (! is_string($slug) || (! is_string($value) && $value !== null)) {
                return null;
            }

            $clean[$slug] = $value;
        }

        if ($etag !== null && ! is_string($etag)) {
            return null;
        }

        return new self($clean, $etag, $fetchedAt, $staleUntil, $fallback);
    }
}
