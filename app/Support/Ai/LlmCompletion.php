<?php

namespace App\Support\Ai;

/**
 * Resposta crua do provedor de LLM.
 *
 * `content` ainda e o texto devolvido no campo `choices[0].message.content`;
 * quem transforma em contrato e valida o JSON e o job. Os tokens sao os do
 * `usage` do provedor e alimentam o ajuste da reserva.
 */
final readonly class LlmCompletion
{
    public function __construct(
        public string $content,
        public int $tokensIn,
        public int $tokensOut,
        public string $model,
    ) {}
}
