<?php

namespace App\Support\Payments;

/**
 * Cobranca de uma preapproval (renovacao automatica).
 *
 * `paymentId` e o pagamento subjacente, usado no estorno e na idempotencia por
 * `(gateway, gateway_ref)`. `retryAttempt` acima de zero marca uma nova
 * tentativa do provedor para o mesmo periodo.
 */
final readonly class GatewayAuthorizedPayment
{
    public function __construct(
        public string $id,
        public string $status,
        public string $preapprovalId,
        public ?string $paymentId,
        public int $amountCents,
        public string $currency,
        public int $retryAttempt,
    ) {}

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
