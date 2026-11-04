<?php

namespace App\Support\BadgeForge\Providers;

/**
 * Payload **projetado** de um provider: so as metricas que o badge usa.
 *
 * O corpo cru nunca e guardado nem cacheado, para um item privado nao deixar
 * descricao/README no store (F4, ADR-080).
 */
final readonly class ProviderPayload
{
    /**
     * @param  array<string, int|float|string|null>  $metrics
     */
    public function __construct(
        public array $metrics,
        public ?string $etag = null,
        public bool $notModified = false,
    ) {}

    public function metric(string $key): int|float|string|null
    {
        return $this->metrics[$key] ?? null;
    }
}
