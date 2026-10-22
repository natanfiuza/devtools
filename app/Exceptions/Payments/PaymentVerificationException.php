<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * O pagamento aprovado nao casa com o que o site esperava (F6).
 *
 * Nada e liberado quando isto acontece: valor, moeda, preapproval ou plano
 * divergentes sao anomalias que o admin precisa ver, nunca um motivo para
 * conceder acesso por um pagamento que nao corresponde ao combinado.
 */
final class PaymentVerificationException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function amountMismatch(): self
    {
        return new self('Payments: valor pago divergente do checkout.');
    }

    public static function donationAmountMismatch(): self
    {
        return new self('Payments: valor pago divergente da doacao.');
    }

    public static function currencyMismatch(): self
    {
        return new self('Payments: moeda paga divergente do checkout.');
    }

    public static function preapprovalMismatch(): self
    {
        return new self('Payments: cobranca de outra preapproval.');
    }

    public static function missingPreapproval(): self
    {
        return new self('Payments: assinatura recorrente sem preapproval.');
    }

    public static function missingReference(): self
    {
        return new self('Payments: pagamento do gateway sem referencia.');
    }

    public static function planUnavailable(): self
    {
        return new self('Payments: plano do checkout nao aceita novo periodo.');
    }
}
