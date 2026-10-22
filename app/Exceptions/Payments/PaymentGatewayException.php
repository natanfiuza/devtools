<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Falha ao falar com o gateway.
 *
 * A mensagem carrega so a operacao e o status HTTP - nunca o corpo da resposta
 * nem o token. `retryable` separa o que vale tentar de novo (rede, 429, 5xx)
 * do que nao vale (4xx de contrato).
 */
final class PaymentGatewayException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly bool $retryable,
        public readonly ?int $statusCode = null,
        public readonly string $operation = '',
    ) {
        parent::__construct($message);
    }

    public static function connection(string $operation): self
    {
        return new self(
            "Payments: falha de conexao em {$operation}.",
            retryable: true,
            operation: $operation,
        );
    }

    public static function fromStatus(string $operation, int $status): self
    {
        return new self(
            "Payments: {$operation} respondeu HTTP {$status}.",
            retryable: $status === 429 || $status >= 500,
            statusCode: $status,
            operation: $operation,
        );
    }

    public static function invalidResponse(string $operation): self
    {
        return new self(
            "Payments: resposta invalida em {$operation}.",
            retryable: true,
            operation: $operation,
        );
    }

    public static function unavailable(string $operation): self
    {
        return new self(
            "Payments: gateway sem credenciais em {$operation}.",
            retryable: false,
            operation: $operation,
        );
    }

    /**
     * O recurso ja nao existe ou ja esta no estado desejado.
     */
    public function isAlreadyGone(): bool
    {
        return $this->statusCode === 404 || $this->statusCode === 409;
    }
}
