<?php

namespace App\Actions\Colors;

use App\Models\ColorPalette;
use App\Models\ColorPaletteFavorite;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Favorita uma paleta publica de outra conta (F5, ADR-128).
 *
 * Idempotente: favoritar duas vezes devolve a mesma linha. A corrida entre
 * duas abas cai no indice unico e e resolvida relendo o vinculo.
 */
final class FavoritePalette
{
    public function __invoke(User $user, ColorPalette $palette): ColorPaletteFavorite
    {
        try {
            return ColorPaletteFavorite::query()->firstOrCreate([
                'user_id' => $user->getKey(),
                'color_palette_id' => $palette->getKey(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return ColorPaletteFavorite::query()
                ->where('user_id', $user->getKey())
                ->where('color_palette_id', $palette->getKey())
                ->sole();
        }
    }
}
