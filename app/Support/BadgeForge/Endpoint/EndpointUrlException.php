<?php

namespace App\Support\BadgeForge\Endpoint;

use RuntimeException;

/**
 * URL de endpoint recusada (F5, ADR-084).
 *
 * A mensagem e o proprio motivo em ingles tecnico; o texto para o usuario
 * vem da traducao `validation.badge_endpoint.<reason>`. A URL crua nunca
 * entra na excecao, para nao vazar em log ou handler de erro.
 */
final class EndpointUrlException extends RuntimeException
{
    public function __construct(public readonly EndpointUrlReason $reason)
    {
        parent::__construct($reason->value);
    }
}
