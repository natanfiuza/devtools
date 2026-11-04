<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeAsset;
use App\Models\User;
use App\Support\BadgeForge\Personal\PersonalBadgeGuard;

/**
 * Renomeia uma badge pessoal (F4, ADR-081).
 *
 * Provider, params e formulario sao imutaveis: mudar a origem de uma URL ja
 * publicada seria um badge diferente. Recriar e a saida documentada.
 */
final class UpdateBadgeAsset
{
    public function __construct(private readonly PersonalBadgeGuard $guard) {}

    public function __invoke(User $user, BadgeAsset $asset, string $name): BadgeAsset
    {
        $this->guard->ensurePaid($user);

        $asset->forceFill(['name' => $name])->save();

        return $asset;
    }
}
