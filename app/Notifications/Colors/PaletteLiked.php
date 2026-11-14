<?php

namespace App\Notifications\Colors;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

/**
 * Aviso de curtida recebida (F6, ADR-137).
 *
 * A F6 grava so no canal `database`; o sino, a preferencia e o e-mail sao da
 * F7. O ator anonimo nao carrega identificador, IP nem e-mail: o payload so
 * marca `anonymous`.
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
        return ['database'];
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
}
