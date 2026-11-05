<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Corpo cru de uma chamada pinada (F7, ADR-087).
 *
 * `fetchBody` separa a conexao validada do parser de contrato: o endpoint
 * shields v1 e o provider customizado usam a mesma pinagem SSRF, mas decodam
 * documentos diferentes. Em 304 nao ha corpo e `notModified` vem true.
 */
final readonly class EndpointBodyResult
{
    public function __construct(
        public ?string $body,
        public ?string $etag = null,
        public bool $notModified = false,
    ) {}
}
