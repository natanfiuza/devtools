<?php

namespace App\Exceptions\Ai;

/**
 * A resposta do modelo nao segue o contrato JSON do SPEC.
 *
 * O motivo e um rotulo curto (`missing_sql`, `dialect_mismatch`, ...), nunca o
 * trecho recebido nem o prompt que o gerou.
 */
final class InvalidContractException extends AiException
{
    public static function because(string $reason): self
    {
        return new self(sprintf('AI response does not match the contract: %s.', $reason));
    }
}
