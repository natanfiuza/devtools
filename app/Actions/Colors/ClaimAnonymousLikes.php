<?php

namespace App\Actions\Colors;

use App\Models\ColorPalette;
use App\Models\ColorPaletteLike;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Reivindica para a conta as curtidas feitas antes do login (F6, ADR-135).
 *
 * O identificador anonimo e descartado ao terminar: as linhas ativas passam a
 * pertencer a conta. Quando a conta ja curtiu a mesma paleta, as duas linhas
 * viram uma - a ativa vence e `notified_at` guarda o primeiro aviso nao nulo,
 * para que o dono nunca receba um segundo aviso pela mesma relacao. Linhas
 * inativas, de paleta do propria conta e de paleta inexistente sao removidas.
 *
 * A operacao e idempotente e tolera duas reivindicacoes concorrentes: a corrida
 * cai no unique e a linha vencedora e relida.
 */
final class ClaimAnonymousLikes
{
    private const PATTERN = '/^[a-f0-9]{64}$/';

    public function __invoke(User $user, string $anonymousId): int
    {
        if (preg_match(self::PATTERN, $anonymousId) !== 1) {
            return 0;
        }

        return DB::transaction(function () use ($user, $anonymousId): int {
            $likes = ColorPaletteLike::query()
                ->where('anonymous_id', $anonymousId)
                ->get();

            $claimed = 0;

            foreach ($likes as $like) {
                // `find` devolve `?ColorPalette`; a paleta pode ter sumido
                // entre o like e o login.
                $palette = ColorPalette::query()->find($like->color_palette_id);

                // Paleta desaparecida ou da propria conta: o vinculo anonimo
                // nao tem para onde ir.
                if ($palette === null || (int) $palette->user_id === (int) $user->getKey()) {
                    $like->delete();

                    continue;
                }

                // Sem curtida ativa nao ha o que reivindicar; a linha anonima
                // e descartada para nao reaparecer num cookie restaurado.
                if (! $like->isActive()) {
                    $like->delete();

                    continue;
                }

                $this->merge($like, $user);
                $claimed++;
            }

            return $claimed;
        });
    }

    private function merge(ColorPaletteLike $anonymous, User $user): void
    {
        try {
            $existing = ColorPaletteLike::query()
                ->where('color_palette_id', $anonymous->color_palette_id)
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ColorPaletteLike) {
                $this->mergeRows($existing, $anonymous);

                return;
            }

            $anonymous->forceFill([
                'user_id' => $user->getKey(),
                'anonymous_id' => null,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            // Outra requisicao reivindicou a mesma paleta entre a leitura e a
            // escrita; a linha da conta ja existe e recebe o estado anonimo.
            $existing = ColorPaletteLike::query()
                ->where('color_palette_id', $anonymous->color_palette_id)
                ->where('user_id', $user->getKey())
                ->first();

            if ($existing instanceof ColorPaletteLike && $anonymous->exists) {
                $this->mergeRows($existing, $anonymous);
            }
        }
    }

    private function mergeRows(ColorPaletteLike $existing, ColorPaletteLike $anonymous): void
    {
        if (! $existing->isActive()) {
            $existing->forceFill([
                'liked_at' => $anonymous->liked_at,
                'notified_at' => $existing->notified_at ?? $anonymous->notified_at,
            ])->save();
        } elseif ($existing->notified_at === null && $anonymous->notified_at !== null) {
            $existing->forceFill(['notified_at' => $anonymous->notified_at])->save();
        }

        $anonymous->delete();
    }
}
