<?php

namespace App\Support\BadgeForge\Providers;

use App\Support\BadgeForge\BadgeSpecBuilder;

/**
 * Monta a badge publica de provider (F4, ADR-080).
 *
 * Provider/definicao desconhecidos, path invalido ou URL insegura devolvem
 * `null` (404). O TTL do `Cache-Control` e o do provider limitado pelo teto
 * de cache do BadgeForge; `?cacheSeconds=` e `?refresh=1` do cliente sao
 * ignorados.
 */
final class ProviderBadgeComposer
{
    public function __construct(
        private readonly ProviderCatalog $catalog,
        private readonly ProviderPathParser $parser,
        private readonly ProviderUrlBuilder $urlBuilder,
        private readonly ProviderMetrics $metrics,
        private readonly ProviderMetricFormatter $formatter,
        private readonly BadgeSpecBuilder $builder,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function compose(
        string $providerSlug,
        string $definitionSlug,
        string $path,
        array $query,
    ): ?ProviderBadge {
        $pair = $this->catalog->pair($providerSlug, $definitionSlug);

        if ($pair === null) {
            return null;
        }

        $params = $this->parser->parse($pair->definition, $path);

        if ($params === null) {
            return null;
        }

        try {
            $url = $this->urlBuilder->build($pair, $params);
        } catch (ProviderFetchException) {
            return null;
        }

        $result = $this->metrics->get(new ProviderFetchRequest(
            pair: $pair,
            params: $params,
            url: $url,
        ));

        $message = $this->formatter->format($result->value, $pair->definition->extractor_key)
            ?? (string) config('badgeforge.providers.unavailable_message', 'unavailable');

        $ttl = min(
            $result->ttl,
            (int) config('badgeforge.personal_badges.max_cache_seconds', 3600),
        );

        return new ProviderBadge(
            spec: $this->builder->build(
                $pair->definition->label,
                $message,
                $query,
                max(1, $ttl),
            ),
            status: $result->status,
        );
    }
}
