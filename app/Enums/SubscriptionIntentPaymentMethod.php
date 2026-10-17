<?php

namespace App\Enums;

/**
 * Forma de pagamento preferida informada na intencao de assinatura (ADR-033).
 */
enum SubscriptionIntentPaymentMethod: string
{
    case Pix = 'pix';
    case CreditCard = 'credit_card';
    case Boleto = 'boleto';
}
