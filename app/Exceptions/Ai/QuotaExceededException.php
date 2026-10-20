<?php

namespace App\Exceptions\Ai;

use App\Enums\QuotaUnit;

/**
 * A conta nao tem cota para concluir a solicitacao.
 *
 * `required` e a quantidade que faltou na unidade do plano. Ele serve para a
 * resposta explicar o limite sem revelar token, schema ou prompt.
 */
final class QuotaExceededException extends AiException
{
    public function __construct(
        public readonly QuotaUnit $unit,
        public readonly int $required,
    ) {
        parent::__construct('The AI quota is exhausted.');
    }

    public static function forUnit(QuotaUnit $unit, int $required): self
    {
        return new self($unit, max(0, $required));
    }
}
