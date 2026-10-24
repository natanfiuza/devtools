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
    /**
     * @param  list<array{code: string, description: string}>  $gatewayCauses
     */
    private function __construct(
        string $message,
        public readonly bool $retryable,
        public readonly ?int $statusCode = null,
        public readonly string $operation = '',
        public readonly ?string $gatewayError = null,
        public readonly array $gatewayCauses = [],
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

    /**
     * @param  list<array{code: string, description: string}>  $gatewayCauses
     */
    public static function fromStatus(
        string $operation,
        int $status,
        ?string $gatewayError = null,
        array $gatewayCauses = [],
    ): self {
        return new self(
            "Payments: {$operation} respondeu HTTP {$status}.",
            retryable: $status === 429 || $status >= 500,
            statusCode: $status,
            operation: $operation,
            gatewayError: $gatewayError,
            gatewayCauses: $gatewayCauses,
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

    /**
     * Contexto estruturado para o log do Laravel (sem corpo cru nem segredos).
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return array_filter([
            'operation' => $this->operation !== '' ? $this->operation : null,
            'status_code' => $this->statusCode,
            'gateway_error' => $this->gatewayError,
            'gateway_causes' => $this->gatewayCauses !== [] ? $this->gatewayCauses : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
