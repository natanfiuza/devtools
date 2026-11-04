<?php

namespace App\Support\BadgeForge\Providers;

/**
 * Entrada de cache de provider: apenas as metricas projetadas e metadados
 * (F4, ADR-080). O corpo cru nunca e persistido.
 */
final readonly class CachedProviderPayload
{
    /**
     * @param  array<string, int|float|string|null>  $metrics
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
        return new self($this->metrics, $this->etag, $now, $now + max(1, $staleTtl), false);
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

        if ($etag !== null && ! is_string($etag)) {
            return null;
        }

        foreach ($metrics as $value) {
            if ($value !== null && ! is_int($value) && ! is_float($value) && ! is_string($value)) {
                return null;
            }
        }

        return new self($metrics, $etag, $fetchedAt, $staleUntil, $fallback);
    }
}
