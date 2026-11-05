<?php

namespace App\Enums;

/**
 * Formatadores de uma metrica de provider customizado (F7, ADR-087).
 *
 * `text` normaliza espacos; `number` usa k/M/B; `percent` arredonda e
 * acrescenta `%`. Os dois ultimos reusam `ProviderMetricFormatter`.
 */
enum CustomMetricFormatter: string
{
    case Text = 'text';
    case Number = 'number';
    case Percent = 'percent';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $formatter): string => $formatter->value, self::cases());
    }
}
