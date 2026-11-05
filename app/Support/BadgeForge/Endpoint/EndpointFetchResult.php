<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Resultado do cliente HTTP (F5).
 *
 * `notModified` vem de um 304 condicional e faz a camada de cache renovar
 * `fetched_at` sem trocar o payload.
 */
final readonly class EndpointFetchResult
{
    public function __construct(
        public ?EndpointPayload $payload = null,
        public ?string $etag = null,
        public bool $notModified = false,
    ) {}
}
