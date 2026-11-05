<?php

namespace App\Http\Resources;

use App\Models\BadgeEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Endpoint JSON na API de gestao do BadgeForge (F9, ADR-090).
 *
 * A URL de origem nunca sai: o recurso publico e identificado pelo ULID. A
 * representacao carrega o que o painel mostra - nome e as duas URLs publicas.
 *
 * @property BadgeEndpoint $resource
 */
final class ApiBadgeEndpointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BadgeEndpoint $endpoint */
        $endpoint = $this->resource;

        return [
            'id' => (string) $endpoint->id,
            'name' => (string) $endpoint->name,
            'svgUrl' => $endpoint->publicSvgUrl(),
            'pngUrl' => $endpoint->publicPngUrl(),
            'createdAt' => $endpoint->created_at?->toIso8601String(),
        ];
    }
}
