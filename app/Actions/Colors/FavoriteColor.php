<?php

namespace App\Actions\Colors;

use App\Models\ColorFavorite;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Favorita uma cor da conta (F5, ADR-127).
 *
 * Idempotente: a mesma cor favoritada duas vezes continua sendo uma linha.
 */
final class FavoriteColor
{
    public function __invoke(User $user, string $color): ColorFavorite
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return ColorFavorite::query()->firstOrCreate([
                    'user_id' => $user->getKey(),
                    'color' => $color,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }
}
