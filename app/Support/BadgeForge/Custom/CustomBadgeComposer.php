<?php

namespace App\Support\BadgeForge\Custom;

use App\Models\BadgeCustomProvider;
use App\Models\BadgeCustomProviderDefinition;
use App\Support\BadgeForge\BadgeSpecBuilder;

/**
 * Monta a badge publica de um provider customizado (F7, ADR-087).
 *
 * O label vem da definicao (editavel); o valor vem do cache/fetch e a falha
 * vira `unavailable`. Query cosmetica funciona como nas demais rotas;
 * `?cacheSeconds=` e `?refresh=1` sao ignorados porque o TTL e do servidor.
 */
final class CustomBadgeComposer
{
    public function __construct(
        private readonly CustomProviderMetrics $metrics,
        private readonly BadgeSpecBuilder $builder,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function compose(
        BadgeCustomProvider $provider,
        BadgeCustomProviderDefinition $definition,
        string $path,
        array $query,
    ): CustomBadge {
        $result = $this->metrics->get($provider, $path);
        $value = $result->metrics[(string) $definition->slug] ?? null;

        $queryLabel = $query['label'] ?? null;
        $label = is_string($queryLabel) && trim($queryLabel) !== ''
            ? $queryLabel
            : (string) $definition->label;

        $message = $value ?? (string) config('badgeforge.custom_providers.unavailable_message', 'unavailable');

        return new CustomBadge(
            spec: $this->builder->build($label, $message, $query, max(1, $result->ttl)),
            status: $result->status,
        );
    }
}
