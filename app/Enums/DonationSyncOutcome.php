<?php

namespace App\Enums;

/**
 * Efeito de uma consulta ao provedor sobre a doacao (ADR-059).
 *
 * `Declined` e `Refunded` dizem o que o provedor informou, mesmo quando a
 * doacao ja estava nesse estado; `Unchanged` cobre pendente, em analise e
 * pagamento ja confirmado.
 */
enum DonationSyncOutcome: string
{
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Refunded = 'refunded';
    case Unchanged = 'unchanged';
}
