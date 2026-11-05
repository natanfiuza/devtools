<?php

namespace App\Http\Resources;

use App\Models\BadgeCustomProvider;
use App\Models\BadgeCustomProviderDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Provider customizado na API de gestao do BadgeForge (F9, ADR-090).
 *
 * A origem (`url_template`) nunca sai, nem para o dono: o recurso carrega o
 * que o painel mostra - nome, estado, `path_params`, `example_path` e as
 * metricas declaradas com o `pointer`. A imagem publica e identificada pelo
 * ULID e pelo slug da metrica.
 *
 * @property BadgeCustomProvider $resource
 */
final class ApiBadgeCustomProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BadgeCustomProvider $provider */
        $provider = $this->resource;

        return [
            'id' => (string) $provider->id,
            'name' => (string) $provider->name,
            'enabled' => (bool) $provider->enabled,
            'pathParams' => $provider->path_params,
            'examplePath' => (string) $provider->example_path,
            'definitions' => $provider->definitions
                ->map(static fn (BadgeCustomProviderDefinition $definition): array => [
                    'slug' => (string) $definition->slug,
                    'label' => (string) $definition->label,
                    'pointer' => (string) $definition->pointer,
                    'formatter' => $definition->formatter->value,
                ])
                ->values()
                ->all(),
            'createdAt' => $provider->created_at?->toIso8601String(),
        ];
    }
}
