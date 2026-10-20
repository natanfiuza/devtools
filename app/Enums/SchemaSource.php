<?php

namespace App\Enums;

/**
 * Formato em que a pessoa entrega o schema (SPEC secao 6).
 *
 * O formato e so do texto de entrada: o prompt recebe o schema como dado e o
 * dialeto vem separado. `migration` e `models` sao os formatos de framework.
 */
enum SchemaSource: string
{
    case Ddl = 'ddl';
    case Json = 'json';
    case Migration = 'migration';
    case Models = 'models';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
