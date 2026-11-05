<?php

namespace App\Http\Resources;

use App\Models\BadgeCustomLogo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Logo personalizado na API de gestao do BadgeForge (F8, ADR-088).
 *
 * Apenas metadados: `view_box`, `paths` e o SVG cru nunca saem por aqui. Os
 * paths continuam disponiveis so para o dono autenticado na tela, sob demanda.
 *
 * @property BadgeCustomLogo $resource
 */
final class ApiBadgeLogoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BadgeCustomLogo $logo */
        $logo = $this->resource;

        return [
            'id' => (string) $logo->id,
            'slug' => $logo->slug(),
            'name' => (string) $logo->name,
            'viewBox' => (string) $logo->view_box,
            'pathCount' => (int) $logo->path_count,
            'sizeBytes' => (int) $logo->size_bytes,
            'createdAt' => $logo->created_at?->toIso8601String(),
        ];
    }
}
