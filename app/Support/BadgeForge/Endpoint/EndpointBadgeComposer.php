<?php

namespace App\Support\BadgeForge\Endpoint;

use App\Models\BadgeEndpoint;
use App\Support\BadgeForge\BadgeSpecBuilder;

/**
 * Monta a badge publica de endpoint JSON (F5, ADR-083).
 *
 * A URL de origem so existe no servidor: o guard a valida de novo antes do
 * fetch e o payload projetado monta label/message. Query cosmetica vence os
 * defaults do JSON; `?cacheSeconds=` e `?refresh=1` sao ignorados. Falha de
 * origem vira fallback 200 com o nome do endpoint e a mensagem neutra.
 */
final class EndpointBadgeComposer
{
    public function __construct(
        private readonly EndpointUrlGuard $guard,
        private readonly EndpointMetrics $metrics,
        private readonly BadgeSpecBuilder $builder,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     *
     * @throws EndpointUrlException quando a URL guardada nao passa no guard.
     */
    public function compose(BadgeEndpoint $endpoint, array $query): EndpointBadge
    {
        $url = $this->guard->parse($endpoint->url);
        $result = $this->metrics->get($url);
        $payload = $result->payload;

        $queryLabel = $query['label'] ?? null;
        $label = is_string($queryLabel) && trim($queryLabel) !== ''
            ? $queryLabel
            : ($payload !== null ? $payload->label : $endpoint->name);
        $message = $payload !== null
            ? $payload->message
            : (string) config('badgeforge.endpoint.unavailable_message', 'unavailable');

        return new EndpointBadge(
            spec: $this->builder->build(
                $label,
                $message,
                $this->withPayloadColors($query, $payload),
                max(1, $result->ttl),
            ),
            status: $result->status,
        );
    }

    /**
     * `color`/`labelColor` do JSON sao defaults: a query explicita vence.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function withPayloadColors(array $query, ?EndpointPayload $payload): array
    {
        if ($payload?->color !== null && ! isset($query['color'])) {
            $query['color'] = $payload->color;
        }

        if ($payload?->labelColor !== null && ! isset($query['labelColor'])) {
            $query['labelColor'] = $payload->labelColor;
        }

        return $query;
    }
}
