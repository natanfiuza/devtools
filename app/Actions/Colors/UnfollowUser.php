<?php

namespace App\Actions\Colors;

use App\Models\User;
use App\Models\UserFollow;
use InvalidArgumentException;

/**
 * Deixa de seguir uma conta (F6, ADR-136).
 *
 * A linha nao e apagada: `followed_at` volta a nulo e `notified_at` permanece,
 * entao seguir de novo nao avisa outra vez. Sem vinculo ativo, nada acontece.
 */
final class UnfollowUser
{
    public function __invoke(User $follower, User $followed): void
    {
        if ((int) $follower->getKey() === (int) $followed->getKey()) {
            throw new InvalidArgumentException('UnfollowUser nao aceita auto-seguimento.');
        }

        $follow = UserFollow::query()
            ->where('follower_id', $follower->getKey())
            ->where('followed_id', $followed->getKey())
            ->first();

        if ($follow instanceof UserFollow && $follow->isActive()) {
            $follow->forceFill(['followed_at' => null])->save();
        }
    }
}
