<?php

namespace App\Support\Payments;

use Carbon\CarbonInterface;

/**
 * Pedido de cobranca Pix avulsa (doacao).
 */
final readonly class PixChargeRequest
{
    public function __construct(
        public string $idempotencyKey,
        public string $externalReference,
        public string $description,
        public int $amountCents,
        public string $currency,
        public ?string $payerEmail,
        public ?string $notificationUrl,
        public ?CarbonInterface $expiresAt,
    ) {}
}
