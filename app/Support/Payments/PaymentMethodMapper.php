<?php

namespace App\Support\Payments;

use App\Enums\PaymentMethod;

/**
 * Traduz o meio de pagamento do provedor para o enum local.
 *
 * O `payment_type_id` e mais estavel que o `payment_method_id` (que muda por
 * bandeira); o segundo entra como fallback porque nem toda resposta traz o
 * primeiro.
 */
final class PaymentMethodMapper
{
    public static function fromGateway(?string $typeId, ?string $methodId = null): PaymentMethod
    {
        $type = mb_strtolower(trim((string) $typeId));
        $method = mb_strtolower(trim((string) $methodId));

        return match (true) {
            $type === 'pix' || $method === 'pix' => PaymentMethod::Pix,
            $type === 'credit_card' || $type === 'debit_card' => PaymentMethod::CreditCard,
            $type === 'ticket' || $method === 'bolbradesco' || $method === 'pec' => PaymentMethod::Boleto,
            default => PaymentMethod::Other,
        };
    }
}
