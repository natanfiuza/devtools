<?php

namespace App\Enums;

/**
 * Estado de um checkout em andamento.
 *
 * O estado so avanca por webhook ou por expiracao: o retorno do navegador nao
 * confirma pagamento. `approved` e terminal e marca a sessao que ja abriu
 * periodo e gravou o pagamento; `failed` guarda a falha do gateway na criacao.
 */
enum CheckoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Failed = 'failed';
    case Expired = 'expired';
    case Canceled = 'canceled';

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
