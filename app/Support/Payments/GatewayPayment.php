<?php

namespace App\Support\Payments;

use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;

/**
 * Pagamento consultado no provedor.
 *
 * O status e o cru do provedor (`approved`, `pending`, `refunded`...): quem
 * decide o que fazer com ele e a acao, nao o cliente HTTP. O `method` ja vem
 * mapeado para o enum local; `methodId` guarda o valor original para o
 * historico do admin.
 */
final readonly class GatewayPayment
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $statusDetail,
        public int $amountCents,
        public string $currency,
        public ?string $externalReference,
        public PaymentMethod $method,
        public ?string $methodId,
        public ?CarbonInterface $paidAt,
    ) {}

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRefunded(): bool
    {
        return in_array($this->status, ['refunded', 'charged_back'], true);
    }
}
