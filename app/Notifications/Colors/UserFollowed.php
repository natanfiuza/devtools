<?php

namespace App\Notifications\Colors;

use App\Models\ColorNotificationPreference;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de novo seguidor (F6, ADR-137; e-mail na F7, ADR-142).
 *
 * `database` sempre; `mail` so quando a conta ligou o toggle de seguidores. O
 * silencio por paleta nao alcanca follows: ele e do dono da paleta sobre o
 * que acontece nela, e seguir e uma relacao de conta.
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
        if (! $notifiable instanceof User
            || $notifiable->isSuspended()
            || $notifiable->isAnonymized()) {
            return [];
        }

        $channels = ['database'];

        if (ColorNotificationPreference::for($notifiable)->allowsEmail('follow')) {
            $channels[] = 'mail';
        }

        return $channels;
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

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $message = (new MailMessage)
            ->subject(__('mail.colors.follow.subject'))
            ->greeting(__('mail.colors.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.colors.follow.intro', ['actor' => $this->followerName]))
            ->line(__('mail.colors.manage_hint'));

        if ($this->followerUsername !== null) {
            $message->action(__('mail.colors.follow.action'), route('colors.profiles.show', [
                'locale' => Locales::slug((string) $notifiable->preferredLocale()),
                'username' => $this->followerUsername,
            ]));
        }

        return $message;
    }
}
