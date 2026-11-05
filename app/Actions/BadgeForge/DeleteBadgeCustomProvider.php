<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeCustomProvider;

/**
 * Exclui um provider customizado (F7, ADR-087).
 *
 * Definicoes saem por cascade. A URL publica passa a responder 404; entradas
 * de cache expiram sozinhas pelo `stale_ttl` (a chave e por URL resolvida, e o
 * dono pode ter varios paths).
 */
final class DeleteBadgeCustomProvider
{
    public function __invoke(BadgeCustomProvider $provider): void
    {
        $provider->delete();
    }
}
