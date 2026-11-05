<?php

namespace App\Support\BadgeForge\Custom;

use App\Enums\CustomMetricFormatter;

/**
 * Normaliza as metricas vindas do formulario (F7, ADR-087).
 *
 * A action tambem roda fora do FormRequest nos testes e em chamadas internas;
 * esta classe garante que so entram linhas completas, com slug unico e
 * formatador valido. O request ja validou o shape visivel.
 */
final class CustomMetricInput
{
    /**
     * @return list<array{slug: string, label: string, pointer: string, formatter: CustomMetricFormatter}>|null
     */
    public static function listFrom(mixed $metrics): ?array
    {
        if (! is_array($metrics) || $metrics === []) {
            return null;
        }

        $normalized = [];
        $seen = [];

        foreach ($metrics as $metric) {
            if (! is_array($metric)) {
                return null;
            }

            $slug = $metric['slug'] ?? null;
            $label = $metric['label'] ?? null;
            $pointer = $metric['pointer'] ?? null;
            $formatter = $metric['formatter'] ?? null;

            if (! is_string($slug) || ! is_string($label) || ! is_string($pointer)) {
                return null;
            }

            if (isset($seen[$slug])) {
                return null;
            }

            $seen[$slug] = true;

            $enum = $formatter instanceof CustomMetricFormatter
                ? $formatter
                : (is_string($formatter) ? CustomMetricFormatter::tryFrom($formatter) : null);

            if ($enum === null) {
                return null;
            }

            $normalized[] = [
                'slug' => $slug,
                'label' => $label,
                'pointer' => $pointer,
                'formatter' => $enum,
            ];
        }

        return $normalized;
    }
}
