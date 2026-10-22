<?php

namespace App\Support\Payments;

/**
 * Preapproval devolvida pelo provedor.
 *
 * `status` e o estado cru (`pending`, `authorized`, `canceled`...). Autorizar
 * nao libera cota: quem abre o periodo e o primeiro pagamento autorizado.
 */
final readonly class GatewayPreapproval
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $initPoint,
        public ?int $amountCents,
        public ?string $currency,
        public ?string $externalReference,
    ) {}

    public function isAuthorized(): bool
    {
        return $this->status === 'authorized';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }
}
