<?php

namespace App\Support\BadgeForge\Custom;

use App\Enums\CustomMetricFormatter as Formatter;
use App\Support\BadgeForge\BadgeRequest;
use App\Support\BadgeForge\Providers\ProviderMetricFormatter;

/**
 * Formata o escalar extraido de um JSON Pointer (F7, ADR-087).
 *
 * Reusa as mesmas regras do catalogo global: `number` usa k/M/B, `percent`
 * exige 0–100 e arredonda, `text` normaliza espacos. O resultado respeita o
 * limite de texto do badge estatico.
 */
final class CustomMetricFormatter
{
    public function __construct(private readonly ProviderMetricFormatter $base) {}

    public function format(mixed $value, Formatter $formatter): ?string
    {
        $text = match ($formatter) {
            Formatter::Text => $this->base->text($value),
            Formatter::Number => $this->base->number($value),
            Formatter::Percent => $this->base->coverage($value),
        };

        if ($text === null) {
            return null;
        }

        return BadgeRequest::truncate(
            $text,
            max(1, (int) config('badgeforge.limits.max_text_length', 100)),
        );
    }
}
