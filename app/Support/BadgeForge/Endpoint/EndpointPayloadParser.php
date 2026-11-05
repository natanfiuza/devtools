<?php

namespace App\Support\BadgeForge\Endpoint;

use JsonException;

/**
 * Parser do contrato shields v1 (F5, ADR-083).
 *
 * O contrato aceito e estreito de proposito: `schemaVersion === 1`, `label` e
 * `message` strings nao vazias, `color`/`labelColor` opcionais. Qualquer outro
 * campo do JSON e ignorado, nunca persistido.
 */
final class EndpointPayloadParser
{
    /**
     * @throws EndpointFetchException
     */
    public function parse(string $body): EndpointPayload
    {
        $maxDepth = max(1, (int) config('badgeforge.endpoint.max_json_depth', 32));

        try {
            $data = json_decode($body, true, $maxDepth, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new EndpointFetchException(EndpointFetchReason::InvalidResponse);
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new EndpointFetchException(EndpointFetchReason::InvalidResponse);
        }

        if (($data['schemaVersion'] ?? null) !== 1) {
            throw new EndpointFetchException(EndpointFetchReason::InvalidResponse);
        }

        $label = $data['label'] ?? null;
        $message = $data['message'] ?? null;

        if (! is_string($label) || trim($label) === ''
            || ! is_string($message) || trim($message) === '') {
            throw new EndpointFetchException(EndpointFetchReason::InvalidResponse);
        }

        return new EndpointPayload(
            label: trim($label),
            message: trim($message),
            color: $this->color($data['color'] ?? null),
            labelColor: $this->color($data['labelColor'] ?? null),
        );
    }

    private function color(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
