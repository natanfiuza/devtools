<?php

namespace App\Actions\Colors;

use App\Models\ColorFavorite;
use App\Models\User;

/**
 * Remove uma cor dos favoritos da conta (F5, ADR-127).
 *
 * Idempotente: remover uma cor que nao estava favoritada nao e erro.
 */
final class UnfavoriteColor
{
    public function __invoke(User $user, string $color): void
    {
        ColorFavorite::query()
            ->forUser($user->getKey())
            ->where('color', $color)
            ->delete();
    }
}
