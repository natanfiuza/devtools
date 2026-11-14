<?php

namespace App\Actions\Colors;

use App\Models\ColorPalette;
use App\Models\ColorPaletteLike;
use App\Models\User;
use App\Notifications\Colors\PaletteLiked;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Curte uma paleta publica (F6, ADR-135).
 *
 * O ator e a conta ou o identificador anonimo, nunca os dois. A linha nasce
 * inativa (`liked_at` nulo) para que o unique do banco resolva a corrida entre
 * duas abas; reativar e so preencher `liked_at`. O aviso ao dono e reivindicado
 * com um UPDATE condicional (`notified_at IS NULL`), o que garante no maximo um
 * aviso por relacao mesmo com requisicoes concorrentes.
 */
final class LikePalette
{
    public function __invoke(ColorPalette $palette, ?User $user, ?string $anonymousId): ColorPaletteLike
    {
        if (($user === null) === ($anonymousId === null)) {
            throw new InvalidArgumentException('LikePalette precisa de exatamente um ator.');
        }

        $actor = $user instanceof User
            ? ['user_id' => $user->getKey()]
            : ['anonymous_id' => $anonymousId];

        $attributes = ['color_palette_id' => $palette->getKey()] + $actor;

        return DB::transaction(function () use ($palette, $user, $attributes): ColorPaletteLike {
            $like = $this->lockLike($attributes);

            if (! $like->isActive()) {
                $like->forceFill(['liked_at' => now()])->save();
            }

            $this->notifyOnce($like, $palette, $user);

            return $like->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lockLike(array $attributes): ColorPaletteLike
    {
        try {
            return ColorPaletteLike::query()->firstOrCreate($attributes);
        } catch (UniqueConstraintViolationException) {
            // Outra requisicao inseriu a mesma linha entre a leitura e o
            // INSERT; reler com lock devolve a linha vencedora.
            return ColorPaletteLike::query()
                ->where($attributes)
                ->lockForUpdate()
                ->sole();
        }
    }

    private function notifyOnce(ColorPaletteLike $like, ColorPalette $palette, ?User $user): void
    {
        $claimed = ColorPaletteLike::query()
            ->whereKey($like->getKey())
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        // `find` devolve `?User`: conta excluida (soft delete) nao aparece e
        // nao recebe aviso.
        $owner = User::query()->find($palette->user_id);

        // O aviso nao existe para o dono da propria paleta, conta removida ou
        // suspensa. `notified_at` fica marcado de qualquer forma: a relacao ja
        // foi avisada (ou dispensada) e nao deve produzir aviso depois.
        if (! $owner instanceof User || $owner->isSuspended() || $owner->isAnonymized()) {
            return;
        }

        if ($user instanceof User && (int) $user->getKey() === (int) $owner->getKey()) {
            return;
        }

        $owner->notify(new PaletteLiked(
            paletteId: $palette->id,
            paletteName: $palette->name,
            paletteSlug: $palette->slug,
            actorId: $user?->getKey(),
            actorName: $user?->name,
            actorUsername: $user?->username,
            anonymous: ! $user instanceof User,
        ));
    }
}
