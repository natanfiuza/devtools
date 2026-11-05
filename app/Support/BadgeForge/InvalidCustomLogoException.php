<?php

namespace App\Support\BadgeForge;

use RuntimeException;

/**
 * SVG de logo recusado pelo sanitizador (ADR-078).
 *
 * `reason` e estavel e vira a chave `validation.custom_logo.<reason>`; a
 * mensagem visivel nunca sai daqui. Nao se usa `code` porque `Exception` ja
 * declara essa propriedade (inteira) e o PHP nao aceita redefini-la.
 */
final class InvalidCustomLogoException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
