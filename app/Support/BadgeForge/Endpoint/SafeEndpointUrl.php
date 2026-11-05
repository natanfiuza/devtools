<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * URL de endpoint ja validada e canonicalizada (F5, ADR-084).
 *
 * `canonical` e a forma usada na chave de cache e no fetch; `host` e `port`
 * alimentam o pin de DNS (`CURLOPT_RESOLVE`). A porta e sempre a permitida
 * pela config (443).
 */
final readonly class SafeEndpointUrl
{
    public function __construct(
        public string $canonical,
        public string $host,
        public int $port,
    ) {}
}
