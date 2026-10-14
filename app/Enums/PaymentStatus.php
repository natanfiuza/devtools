<?php

namespace App\Enums;

/**
 * Estado de uma cobranca. Nao ha `pending`: so se registra o que ja foi pago -
 * cobranca em aberto e assunto do gateway (F6).
 */
enum PaymentStatus: string
{
    case Paid = 'paid';
    case Refunded = 'refunded';
}
