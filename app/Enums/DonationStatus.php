<?php

namespace App\Enums;

/**
 * Estado de uma doacao por Pix.
 *
 * `pending` inclui a doacao estatica, que nao tem webhook para confirmar: ela
 * permanece pendente e o texto da tela diz isso. `paid` e terminal para o
 * recibo, que so sai uma vez.
 */
enum DonationStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Refunded = 'refunded';
    case Failed = 'failed';
}
