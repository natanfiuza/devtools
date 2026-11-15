<?php

namespace App\Notifications\Colors;

use App\Models\ColorNotificationPreference;
use App\Models\ColorPaletteMute;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de curtida recebida (F6, ADR-137; canais e e-mail na F7, ADR-142).
 *
 * O canal `database` e sempre ligado; o `mail` so entra quando a conta ligou o
 * toggle de curtidas. O silencio da paleta e consultado no envio (`via()` roda
 * no worker): silenciar depois nao apaga o aviso que ja existe no sino, apenas
 * impede os proximos. O ator anonimo nao carrega identificador, IP nem e-mail.
 */
final class PaletteLiked extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly string $paletteId,
        public readonly string $paletteName,
        public readonly ?string $paletteSlug,
        public readonly ?int $actorId,
        public readonly ?string $actorName,
        public readonly ?string $actorUsername,
        public readonly bool $anonymous,
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

        if (ColorPaletteMute::isMuted($notifiable, $this->paletteId)) {
            return [];
        }

        $channels = ['database'];

        if (ColorNotificationPreference::for($notifiable)->allowsEmail('like')) {
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
            'kind' => 'palette_liked',
            'palette_id' => $this->paletteId,
            'palette_name' => $this->paletteName,
            'palette_slug' => $this->paletteSlug,
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
            'actor_username' => $this->actorUsername,
            'anonymous' => $this->anonymous,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $actor = $this->actorName ?? __('mail.colors.someone');

        $message = (new MailMessage)
            ->subject(__('mail.colors.like.subject'))
            ->greeting(__('mail.colors.greeting', ['name' => $notifiable->name]))
            ->line($this->anonymous
                ? __('mail.colors.like.intro_anonymous', ['palette' => $this->paletteName])
                : __('mail.colors.like.intro', [
                    'actor' => $actor,
                    'palette' => $this->paletteName,
                ]))
            ->line(__('mail.colors.manage_hint'));

        if ($this->paletteSlug !== null) {
            $message->action(__('mail.colors.like.action'), route('colors.palettes.show', [
                'locale' => Locales::slug((string) $notifiable->preferredLocale()),
                'slug' => $this->paletteSlug,
            ]));
        }

        return $message;
    }
}
