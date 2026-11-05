<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeAsset;
use App\Support\BadgeForge\Personal\BadgeAssetStorage;

/**
 * Exclui uma badge pessoal e seus arquivos (F4, ADR-081).
 *
 * Nao exige plano pago: depois de um downgrade o dono continua podendo
 * copiar a URL das badges existentes e exclui-las.
 */
final class DeleteBadgeAsset
{
    public function __construct(private readonly BadgeAssetStorage $storage) {}

    public function __invoke(BadgeAsset $asset): void
    {
        $this->storage->delete($asset->svg_path);
        $this->storage->delete($asset->png_path);

        $asset->delete();
    }
}
