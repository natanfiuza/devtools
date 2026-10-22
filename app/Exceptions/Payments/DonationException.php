<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Recusa de negocio ao iniciar uma doacao (F6).
 *
 * Como no CheckoutException, nao houve falha do provedor: a regra de produto
 * disse nao. `messageKey` e a chave de traducao que a tela mostra.
 */
final class DonationException extends RuntimeException
{
    private function __construct(
        public readonly string $messageKey,
        string $reason,
    ) {
        parent::__construct($reason);
    }

    /**
     * Nem gateway nem Pix estatico configurados: nao ha como doar.
     */
    public static function unavailable(): self
    {
        return new self('donations.errors.unavailable', 'donation_unavailable');
    }

    /**
     * O provedor exige e-mail do pagador e nao ha nem doador nem fallback.
     */
    public static function payerMissing(): self
    {
        return new self('donations.errors.payer', 'donation_payer_missing');
    }
}
