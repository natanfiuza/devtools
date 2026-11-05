<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Destino validado de uma chamada de endpoint (F5, ADR-084).
 *
 * `ip` e o endereco publico escolhido; ele vai para o `CURLOPT_RESOLVE` e
 * impede que a conexao resolva o host de novo (DNS rebinding).
 */
final readonly class ResolvedEndpoint
{
    public function __construct(
        public SafeEndpointUrl $url,
        public string $ip,
    ) {}

    /**
     * Entrada de `CURLOPT_RESOLVE`: HOST:PORT:ADDRESS, com IPv6 entre
     * colchetes.
     */
    public function resolveEntry(): string
    {
        $address = str_contains($this->ip, ':') ? '['.$this->ip.']' : $this->ip;

        return $this->url->host.':'.$this->url->port.':'.$address;
    }
}
