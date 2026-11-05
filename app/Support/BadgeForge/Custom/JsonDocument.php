<?php

namespace App\Support\BadgeForge\Custom;

use App\Support\BadgeForge\Endpoint\EndpointFetchException;
use App\Support\BadgeForge\Endpoint\EndpointFetchReason;
use JsonException;

/**
 * Documento JSON de um provider customizado (F7, ADR-087).
 *
 * O corpo e `json_decode` com profundidade limitada e erro explicito; raiz
 * escalar, lista vazia ou JSON invalido viram `invalid_response`, que o cache
 * converte em stale/fallback. Nada do documento e persistido.
 */
final class JsonDocument
{
    /**
     * @return array<mixed>
     *
     * @throws EndpointFetchException
     */
    public function decode(string $body): array
    {
        $maxDepth = max(1, (int) config('badgeforge.custom_providers.max_json_depth', 32));

        try {
            $data = json_decode($body, true, $maxDepth, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new EndpointFetchException(EndpointFetchReason::InvalidResponse);
        }

        if (! is_array($data) || $data === []) {
            throw new EndpointFetchException(EndpointFetchReason::InvalidResponse);
        }

        return $data;
    }
}
