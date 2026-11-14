<?php

namespace App\Actions\Colors;

use App\Models\User;
use App\Models\UserFollow;
use App\Notifications\Colors\UserFollowed;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Passa a seguir uma conta (F6, ADR-136).
 *
 * A linha e permanente e unica por par; seguir de novo so reativa
 * `followed_at`. O aviso ao seguido e reivindicado com um UPDATE condicional,
 * entao a mesma relacao nunca gera dois avisos, mesmo com requisicoes
 * concorrentes. Seguir a si mesmo e erro de programacao: o controller responde
 * 403 antes de chegar aqui.
 */
final class FollowUser
{
    public function __invoke(User $follower, User $followed): UserFollow
    {
        if ((int) $follower->getKey() === (int) $followed->getKey()) {
            throw new InvalidArgumentException('FollowUser nao aceita auto-seguimento.');
        }

        return DB::transaction(function () use ($follower, $followed): UserFollow {
            $follow = $this->lockFollow($follower, $followed);

            if (! $follow->isActive()) {
                $follow->forceFill(['followed_at' => now()])->save();
            }

            $this->notifyOnce($follow, $follower, $followed);

            return $follow->refresh();
        });
    }

    private function lockFollow(User $follower, User $followed): UserFollow
    {
        $attributes = [
            'follower_id' => $follower->getKey(),
            'followed_id' => $followed->getKey(),
        ];

        try {
            return UserFollow::query()->firstOrCreate($attributes);
        } catch (UniqueConstraintViolationException) {
            return UserFollow::query()
                ->where($attributes)
                ->lockForUpdate()
                ->sole();
        }
    }

    private function notifyOnce(UserFollow $follow, User $follower, User $followed): void
    {
        $claimed = UserFollow::query()
            ->whereKey($follow->getKey())
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        if ($followed->isSuspended() || $followed->isAnonymized()) {
            return;
        }

        $followed->notify(new UserFollowed(
            followerId: (int) $follower->getKey(),
            followerName: $follower->name,
            followerUsername: $follower->username,
        ));
    }
}
