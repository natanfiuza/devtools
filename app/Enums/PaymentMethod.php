<?php

namespace App\Enums;

/**
 * Como a cobranca manual foi paga. Cartao e boleto chegam com o gateway (F6).
 */
enum PaymentMethod: string
{
    case Pix = 'pix';
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Other = 'other';
}
