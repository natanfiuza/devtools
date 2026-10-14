<?php

namespace App\Enums;

/**
 * Natureza de um lancamento do livro-razao do consumo.
 *
 * `debit` e o uso normal; `refund` devolve o que uma falha consumiu; `grant` e
 * a concessao manual de cota pelo admin. Estorno e concessao andam no mesmo
 * sentido na matematica da cota.
 */
enum LedgerEntryType: string
{
    case Debit = 'debit';
    case Refund = 'refund';
    case Grant = 'grant';

    /**
     * Lancamentos que devolvem cota (contrario do debito).
     *
     * @return list<string>
     */
    public static function creditValues(): array
    {
        return [self::Refund->value, self::Grant->value];
    }
}
