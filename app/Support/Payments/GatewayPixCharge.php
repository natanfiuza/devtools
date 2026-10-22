<?php

namespace App\Support\Payments;

use Carbon\CarbonInterface;

/**
 * Cobranca Pix criada no provedor, com o que a tela precisa para pagar.
 */
final readonly class GatewayPixCharge
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $qrCode,
        public ?string $qrCodeBase64,
        public ?string $ticketUrl,
        public ?CarbonInterface $expiresAt,
    ) {}
}
