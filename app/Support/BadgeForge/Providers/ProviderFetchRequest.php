<?php

namespace App\Support\BadgeForge\Providers;

/**
 * Pedido de fetch ja resolvido: par provider/definicao, params, URL final,
 * credencial opcional e ETag para requisicao condicional (F4, ADR-080).
 */
final readonly class ProviderFetchRequest
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        public ProviderPair $pair,
        public array $params,
        public string $url,
        public ?ProviderCredential $credential = null,
        public ?string $etag = null,
    ) {}

    public function isPrivate(): bool
    {
        return $this->credential !== null;
    }
}
