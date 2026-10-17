<?php

namespace App\Enums;

/**
 * Estado de uma intencao de assinatura (ADR-033).
 */
enum SubscriptionIntentStatus: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Canceled = 'canceled';
}
