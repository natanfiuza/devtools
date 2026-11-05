<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Pedido ao cliente HTTP do endpoint (F5).
 *
 * O destino ja passou pelo guard e pelo resolver; `etag` e o valor da entrada
 * em cache, usado no `If-None-Match` quando existir.
 */
final readonly class EndpointFetchRequest
{
    public function __construct(
        public ResolvedEndpoint $target,
        public ?string $etag = null,
    ) {}
}
