<?php

namespace App\Http\Resources;

use App\Models\BadgeAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Badge pessoal na API de gestao do BadgeForge (F8, ADR-088).
 *
 * Mesma forma da tela do dono: identidade, coordenadas, estado da credencial
 * (nunca a credencial) e as URLs publicas absolutas. `url`/`pngUrl` ficam
 * nulos enquanto a conta nao tiver username.
 *
 * @property BadgeAsset $resource
 */
final class ApiBadgeAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BadgeAsset $asset */
        $asset = $this->resource;

        return [
            'id' => (string) $asset->getKey(),
            'name' => (string) $asset->name,
            'type' => (string) $asset->type,
            'providerSlug' => $asset->provider_slug,
            'definitionSlug' => $asset->definition_slug,
            'params' => $asset->params,
            'private' => (bool) $asset->private,
            'credentialSet' => $asset->credentialSet(),
            'credentialUsername' => $asset->credential_username,
            'url' => $asset->publicSvgUrl(),
            'pngUrl' => $asset->publicPngUrl(),
            'lastGeneratedAt' => $asset->last_generated_at?->toIso8601String(),
            'expiresAt' => $asset->expires_at?->toIso8601String(),
            'createdAt' => $asset->created_at?->toIso8601String(),
        ];
    }
}
