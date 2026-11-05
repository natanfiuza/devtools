<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeEndpoint;

/**
 * Renomeia um endpoint JSON (F5, ADR-083).
 *
 * A URL de origem e imutavel: mudar para onde a imagem aponta seria uma badge
 * diferente. Recriar e a saida documentada.
 */
final class UpdateBadgeEndpoint
{
    public function __invoke(BadgeEndpoint $endpoint, string $name): BadgeEndpoint
    {
        $endpoint->forceFill(['name' => $name])->save();

        return $endpoint;
    }
}
