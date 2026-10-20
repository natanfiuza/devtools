<?php

namespace App\Enums;

/**
 * Dialeto SQL aceito na entrada e no contrato de saida.
 *
 * Sao os quatro do SPEC; o alvo declara em `output_targets.supported_dialects`
 * quais aceita, entao a lista aqui nao e a regra final.
 */
enum SqlDialect: string
{
    case Mysql = 'mysql';
    case Pgsql = 'pgsql';
    case Sqlsrv = 'sqlsrv';
    case Sqlite = 'sqlite';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
