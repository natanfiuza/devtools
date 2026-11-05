<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Entrada de cache do endpoint: so o payload projetado e metadados (F5,
 * ADR-083). O corpo cru e a URL de origem nunca sao persistidos.
 */
final readonly class CachedEndpointPayload
{
    public function __construct(
        public string $label,
        public string $message,
        public ?string $color,
        public ?string $labelColor,
        public ?string $etag,
        public int $fetchedAt,
        public int $staleUntil,
        public bool $fallback = false,
    ) {}

    public static function fallback(int $now, int $ttl): self
    {
        return new self('', '', null, null, null, $now, $now + max(1, $ttl), true);
    }

    public static function fromPayload(EndpointPayload $payload, ?string $etag, int $now, int $staleTtl): self
    {
        return new self(
            label: $payload->label,
            message: $payload->message,
            color: $payload->color,
            labelColor: $payload->labelColor,
            etag: $etag,
            fetchedAt: $now,
            staleUntil: $now + max(1, $staleTtl),
        );
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
        return new self(
            $this->label,
            $this->message,
            $this->color,
            $this->labelColor,
            $this->etag,
            $now,
            $now + max(1, $staleTtl),
        );
    }

    public function payload(): ?EndpointPayload
    {
        return $this->fallback
            ? null
            : new EndpointPayload($this->label, $this->message, $this->color, $this->labelColor);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'message' => $this->message,
            'color' => $this->color,
            'label_color' => $this->labelColor,
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

        $label = $raw['label'] ?? null;
        $message = $raw['message'] ?? null;
        $color = $raw['color'] ?? null;
        $labelColor = $raw['label_color'] ?? null;
        $etag = $raw['etag'] ?? null;
        $fetchedAt = $raw['fetched_at'] ?? null;
        $staleUntil = $raw['stale_until'] ?? null;
        $fallback = $raw['fallback'] ?? false;

        if (! is_string($label) || ! is_string($message)
            || ! is_int($fetchedAt) || ! is_int($staleUntil) || ! is_bool($fallback)) {
            return null;
        }

        if (($color !== null && ! is_string($color))
            || ($labelColor !== null && ! is_string($labelColor))
            || ($etag !== null && ! is_string($etag))) {
            return null;
        }

        return new self($label, $message, $color, $labelColor, $etag, $fetchedAt, $staleUntil, $fallback);
    }
}
