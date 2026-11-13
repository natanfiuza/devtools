<?php

namespace App\Actions\Colors;

use App\Models\ColorPalette;
use App\Models\ColorPaletteFavorite;
use App\Models\User;

/**
 * Remove uma paleta dos favoritos da conta (F5, ADR-128).
 *
 * Idempotente: desfavoritar o que nao existia continua respondendo
 * `favorited: false`.
 */
final class UnfavoritePalette
{
    public function __invoke(User $user, ColorPalette $palette): void
    {
        ColorPaletteFavorite::query()
            ->forUser((int) $user->getKey())
            ->where('color_palette_id', $palette->getKey())
            ->delete();
    }
}
