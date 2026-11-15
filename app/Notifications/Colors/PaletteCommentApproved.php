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
 * Aviso ao dono da paleta quando um comentario e aprovado (F7, ADR-142).
 *
 * So sai na aprovacao: `pending`, `flagged` e `rejected` nao notificam, e a
 * reabertura depois de uma edicao esbarra em `notified_at` no comentario. O
 * corpo do comentario nunca entra no payload nem no e-mail - o aviso leva ao
 * comentario, nao o reproduz. Silenciar a paleta corta o sino e o e-mail.
 */
final class PaletteCommentApproved extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly string $paletteId,
        public readonly string $paletteName,
        public readonly ?string $paletteSlug,
        public readonly string $commentId,
        public readonly ?int $actorId,
        public readonly ?string $actorName,
        public readonly ?string $actorUsername,
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

        if (ColorNotificationPreference::for($notifiable)->allowsEmail('comment')) {
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
            'kind' => 'palette_comment_approved',
            'palette_id' => $this->paletteId,
            'palette_name' => $this->paletteName,
            'palette_slug' => $this->paletteSlug,
            'comment_id' => $this->commentId,
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
            'actor_username' => $this->actorUsername,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $message = (new MailMessage)
            ->subject(__('mail.colors.comment.subject', ['palette' => $this->paletteName]))
            ->greeting(__('mail.colors.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.colors.comment.intro', [
                'actor' => $this->actorName ?? __('mail.colors.someone'),
                'palette' => $this->paletteName,
            ]))
            ->line(__('mail.colors.manage_hint'));

        if ($this->paletteSlug !== null) {
            $message->action(__('mail.colors.comment.action'), route('colors.palettes.show', [
                'locale' => Locales::slug((string) $notifiable->preferredLocale()),
                'slug' => $this->paletteSlug,
            ]).'#comment-'.$this->commentId);
        }

        return $message;
    }
}
