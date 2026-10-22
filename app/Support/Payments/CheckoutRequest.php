<?php

namespace App\Support\Payments;

use Carbon\CarbonInterface;

/**
 * Pedido de Checkout Pro (pagamento avulso).
 *
 * O valor e a moeda vem do plano e ficam congelados aqui: o webhook valida o
 * que foi pago contra este pedido, nunca contra o preco atual do catalogo.
 */
final readonly class CheckoutRequest
{
    public function __construct(
        public string $idempotencyKey,
        public string $externalReference,
        public string $title,
        public int $amountCents,
        public string $currency,
        public ?string $payerEmail,
        public ?string $notificationUrl,
        public ?string $backUrl,
        public ?CarbonInterface $expiresAt,
        public ?string $statementDescriptor = null,
    ) {}
}
