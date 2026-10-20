<?php

namespace App\Exceptions\Ai;

/**
 * Falha ao falar com o provedor.
 *
 * O corpo da resposta nunca entra na mensagem: o provedor pode repetir o
 * schema ou a descricao no erro, e isso nao pode ir para log nem para o banco.
 * `retryable` decide se o job tenta de novo; `reason` e um codigo tecnico
 * estavel para diagnostico.
 */
final class ProviderException extends AiException
{
    private function __construct(
        public readonly bool $retryable,
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function fromStatus(int $status): self
    {
        $reason = 'http_'.$status;

        if ($status === 408 || $status === 429 || $status >= 500) {
            return new self(true, $reason, 'The AI provider is temporarily unavailable.');
        }

        return new self(false, $reason, 'The AI provider rejected the request.');
    }

    public static function retryable(string $reason): self
    {
        return new self(true, $reason, 'The AI provider is temporarily unavailable.');
    }

    public static function invalidResponse(): self
    {
        return new self(true, 'invalid_response', 'The AI provider returned an invalid response.');
    }
}
