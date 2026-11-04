<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Resolucao DNS do host do endpoint (F5).
 *
 * A interface existe para os testes injetarem respostas deterministicas; a
 * implementacao real nunca e chamada com host de IP literal.
 */
interface HostResolver
{
    /**
     * @return list<string> enderecos IPv4/IPv6 resolvidos
     */
    public function resolve(string $host): array;
}
