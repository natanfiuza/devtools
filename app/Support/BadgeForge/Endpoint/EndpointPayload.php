<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Conteudo projetado de um endpoint shields v1 (F5, ADR-083).
 *
 * So os campos que a badge exibe sao guardados; `logoSvg`, `namedLogo`,
 * `style`, `cacheSeconds` e `link` do JSON ficam de fora. O corpo cru nunca
 * vai para o cache.
 */
final readonly class EndpointPayload
{
    public function __construct(
        public string $label,
        public string $message,
        public ?string $color = null,
        public ?string $labelColor = null,
    ) {}
}
