<?php

namespace App\Actions\Colors;

use App\Models\ColorPalette;
use App\Models\ColorPaletteLike;
use App\Models\User;
use InvalidArgumentException;

/**
 * Descurte uma paleta publica (F6, ADR-135).
 *
 * A linha nao e apagada: `liked_at` volta a nulo e `notified_at` e preservado,
 * entao curtir de novo nao avisa o dono outra vez. Sem linha, nao ha o que
 * fazer - a operacao continua idempotente.
 */
final class UnlikePalette
{
    public function __invoke(ColorPalette $palette, ?User $user, ?string $anonymousId): void
    {
        if (($user === null) === ($anonymousId === null)) {
            throw new InvalidArgumentException('UnlikePalette precisa de exatamente um ator.');
        }

        $attributes = ['color_palette_id' => $palette->getKey()] + ($user instanceof User
            ? ['user_id' => $user->getKey()]
            : ['anonymous_id' => $anonymousId]);

        $like = ColorPaletteLike::query()->where($attributes)->first();

        if ($like instanceof ColorPaletteLike && $like->isActive()) {
            $like->forceFill(['liked_at' => null])->save();
        }
    }
}
