<?php

namespace App\Actions\Colors;

use App\Enums\ColorCommentStatus;
use App\Models\ColorPaletteComment;
use App\Models\User;
use App\Notifications\Colors\CommentReplyApproved;
use App\Notifications\Colors\PaletteCommentApproved;

/**
 * Avisa os interessados quando um comentario e aprovado (F7, ADR-142).
 *
 * A notificacao nao sai na escrita: enquanto o texto esta `pending` ou
 * `flagged` nao ha o que celebrar, e um rejeitado nao avisa ninguem. O
 * `notified_at` e reivindicado com UPDATE condicional, entao reavaliar o mesmo
 * comentario (reedicao aprovada de novo, admin mexendo) nao duplica o aviso.
 *
 * Destinatarios: o dono da paleta e, numa resposta, o autor do comentario pai.
 * O autor da acao nunca recebe; quando dono e autor do pai sao a mesma conta,
 * sai um aviso so (o de resposta). O silencio da paleta e decidido no envio,
 * em `via()`, e nao aqui.
 */
final class NotifyApprovedComment
{
    public function __invoke(string $commentId): void
    {
        $comment = ColorPaletteComment::query()
            ->with(['palette', 'parent', 'author'])
            ->find($commentId);

        if (! $comment instanceof ColorPaletteComment
            || $comment->status !== ColorCommentStatus::Approved
            || $comment->deleted_at !== null) {
            return;
        }

        $claimed = ColorPaletteComment::query()
            ->whereKey($commentId)
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $palette = $comment->palette;
        $actor = $comment->author;
        $actorId = $comment->user_id !== null ? (int) $comment->user_id : null;
        $notified = [];

        $parentAuthorId = $comment->parent?->user_id;
        if ($parentAuthorId !== null && (int) $parentAuthorId !== $actorId) {
            $parentAuthor = User::query()->find($parentAuthorId);

            if ($this->canReceive($parentAuthor)) {
                $parentAuthor->notify(new CommentReplyApproved(
                    paletteId: $palette->id,
                    paletteName: $palette->name,
                    paletteSlug: $palette->slug,
                    commentId: $comment->id,
                    actorId: $actorId,
                    actorName: $actor?->name,
                    actorUsername: $actor?->username,
                ));
                $notified[(int) $parentAuthor->getKey()] = true;
            }
        }

        $ownerId = (int) $palette->user_id;

        if ($ownerId === $actorId || isset($notified[$ownerId])) {
            return;
        }

        $owner = User::query()->find($ownerId);

        if (! $this->canReceive($owner)) {
            return;
        }

        $owner->notify(new PaletteCommentApproved(
            paletteId: $palette->id,
            paletteName: $palette->name,
            paletteSlug: $palette->slug,
            commentId: $comment->id,
            actorId: $actorId,
            actorName: $actor?->name,
            actorUsername: $actor?->username,
        ));
    }

    private function canReceive(?User $user): bool
    {
        return $user instanceof User
            && ! $user->trashed()
            && ! $user->isSuspended()
            && ! $user->isAnonymized();
    }
}
