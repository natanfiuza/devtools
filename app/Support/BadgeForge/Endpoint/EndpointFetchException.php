<?php

namespace App\Support\BadgeForge\Endpoint;

use RuntimeException;

/**
 * Falha do fetch de endpoint (F5, ADR-084).
 *
 * A URL nao entra na mensagem: o motivo e o unico dado que pode ir para log
 * ou para a resposta. `retryAfter` so existe quando a origem mandou o
 * cabecalho.
 */
final class EndpointFetchException extends RuntimeException
{
    public function __construct(
        public readonly EndpointFetchReason $reason,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($reason->value);
    }
}
