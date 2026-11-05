<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeEndpoint;
use App\Support\BadgeForge\Endpoint\EndpointCache;
use App\Support\BadgeForge\Endpoint\EndpointCacheKey;
use App\Support\BadgeForge\Endpoint\EndpointUrlException;
use App\Support\BadgeForge\Endpoint\EndpointUrlGuard;

/**
 * Exclui um endpoint JSON e a entrada de cache (F5, ADR-083).
 *
 * A linha e a URL de origem saem juntas; esquecer a chave evita servir um
 * payload de um endpoint que nao existe mais.
 */
final class DeleteBadgeEndpoint
{
    public function __construct(
        private readonly EndpointUrlGuard $urlGuard,
        private readonly EndpointCache $cache,
    ) {}

    public function __invoke(BadgeEndpoint $endpoint): void
    {
        try {
            $this->cache->forget(EndpointCacheKey::for($this->urlGuard->parse($endpoint->url)->canonical));
        } catch (EndpointUrlException) {
            // Linha antiga fora do contrato: nao ha chave valida a esquecer.
        }

        $endpoint->delete();
    }
}
