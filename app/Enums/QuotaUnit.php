<?php

namespace App\Enums;

/**
 * Unidade em que a cota e consumida: usos no Free, tokens nos pagos.
 *
 * A mesma unidade vai para `ai_requests.quota_unit` no inicio da solicitacao -
 * se a assinatura mudar no meio do voo, a liquidacao estorna na unidade em que
 * debitou.
 */
enum QuotaUnit: string
{
    case Uses = 'uses';
    case Tokens = 'tokens';
}
