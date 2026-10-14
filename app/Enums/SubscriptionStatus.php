<?php

namespace App\Enums;

/**
 * Estado contratual de uma assinatura.
 *
 * Nao existe `expired` gravado: a vigencia e resolvida na leitura, pelo
 * `current_period_end` (o deploy nao roda `schedule:run`). `past_due` entra na
 * F6, junto do gateway - hoje so ha cobranca manual.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Canceled = 'canceled';
}
