<?php

namespace App\Exceptions\Ai;

/**
 * O SQL gerado passou pelo contrato, mas nao e uma leitura segura.
 *
 * O motivo nomeia a regra violada, nunca a instrucao inteira: ela pode conter
 * dados do schema.
 */
final class UnsafeSqlException extends AiException
{
    public static function because(string $reason): self
    {
        return new self(sprintf('Generated SQL is not a safe read-only statement: %s.', $reason));
    }
}
