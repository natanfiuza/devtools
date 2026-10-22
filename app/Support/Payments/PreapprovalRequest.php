<?php

namespace App\Support\Payments;

/**
 * Pedido de preapproval (renovacao automatica no cartao).
 *
 * Nao ha plano associado no provedor: a recorrencia e descrita pelo intervalo
 * e o preco fica congelado no `transaction_amount`.
 */
final readonly class PreapprovalRequest
{
    public function __construct(
        public string $idempotencyKey,
        public string $externalReference,
        public string $reason,
        public int $amountCents,
        public string $currency,
        public string $payerEmail,
        public int $frequency,
        public string $frequencyType,
        public ?string $notificationUrl,
        public ?string $backUrl,
    ) {}
}
