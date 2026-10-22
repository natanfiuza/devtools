<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Recusa de negocio ao iniciar um checkout.
 *
 * Diferente de PaymentGatewayException: aqui nao houve falha do provedor - a
 * regra de produto disse nao. `messageKey` e a chave de traducao que a tela
 * mostra, mantendo o texto fora do codigo.
 */
final class CheckoutException extends RuntimeException
{
    private function __construct(
        public readonly string $messageKey,
        string $reason,
    ) {
        parent::__construct($reason);
    }

    public static function gatewayUnavailable(): self
    {
        return new self('home.subscribe.checkout.errors.unavailable', 'gateway_unavailable');
    }

    public static function recurringActive(): self
    {
        return new self('home.subscribe.checkout.errors.recurring_active', 'recurring_active');
    }

    public static function planUnavailable(): self
    {
        return new self('home.subscribe.checkout.errors.plan_unavailable', 'plan_unavailable');
    }
}
