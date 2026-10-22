<?php

namespace App\Enums;

/**
 * Como a cobranca foi paga.
 *
 * Pix, transferencia, dinheiro e "outro" sao os registros manuais (ADR-027);
 * cartao e boleto entram junto do Mercado Pago (F6) e tambem aparecem no
 * registro manual, porque quem recebe por fora pode ter recebido assim.
 */
enum PaymentMethod: string
{
    case Pix = 'pix';
    case Transfer = 'transfer';
    case Cash = 'cash';
    case CreditCard = 'credit_card';
    case Boleto = 'boleto';
    case Other = 'other';
}
