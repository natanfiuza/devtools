<?php

namespace App\Notifications\Colors;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

/**
 * Aviso de novo seguidor (F6, ADR-137).
 *
 * Somente o canal `database` nesta fase; a UI e o e-mail pertencem a F7.
 */
final class UserFollowed extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly int $followerId,
        public readonly string $followerName,
        public readonly ?string $followerUsername,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'user_followed',
            'follower_id' => $this->followerId,
            'follower_name' => $this->followerName,
            'follower_username' => $this->followerUsername,
        ];
    }
}
