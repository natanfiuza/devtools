<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeAsset;
use App\Models\User;
use App\Support\BadgeForge\Personal\PersonalBadge;
use App\Support\BadgeForge\Personal\PersonalBadgeGuard;
use App\Support\BadgeForge\Personal\PersonalBadgeRenderer;

/**
 * Forca a regeneracao de uma badge de provider (F4, ADR-081).
 *
 * A URL publica continua stateless e sem `?refresh=1`: atualizar e um POST
 * autenticado do dono, com throttle proprio. O retorno alimenta o cache-bust
 * local (`?v=<timestamp>`) do painel.
 */
final class RefreshBadgeAsset
{
    public function __construct(
        private readonly PersonalBadgeGuard $guard,
        private readonly PersonalBadgeRenderer $renderer,
    ) {}

    public function __invoke(User $user, BadgeAsset $asset): PersonalBadge
    {
        $this->guard->ensurePaid($user);

        if (! $asset->isProvider()) {
            $this->guard->fail('badge', 'validation.badge_asset.not_provider');
        }

        return $this->renderer->render($asset, true);
    }
}
