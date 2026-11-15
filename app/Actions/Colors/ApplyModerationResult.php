<?php

namespace App\Actions\Colors;

use App\Enums\ColorCommentStatus;
use App\Models\ColorPaletteComment;
use App\Support\Colors\Moderation\ModerationResult;
use Illuminate\Support\Facades\DB;

/**
 * Aplica um veredito somente na revisao que ele moderou (F7, ADR-140).
 *
 * O UPDATE condicional por `revision` + `status = pending` e o que impede um
 * resultado lento de aprovar um texto que o autor ja editou. Quem perdeu a
 * corrida recebe `false` e nao faz nada: o job da revisao nova decide.
 *
 * `flagged` tambem passa por aqui e mantem o comentario visivel com o selo
 * "em analise"; nunca e uma aprovacao.
 */
final class ApplyModerationResult
{
    public function __construct(
        private readonly ResolveColorCommentReports $resolveReports,
        private readonly NotifyApprovedComment $notifyApproved,
    ) {}

    public function __invoke(string $commentId, int $revision, ModerationResult $result): bool
    {
        $approved = $result->status === ColorCommentStatus::Approved;

        $attributes = $approved
            ? [
                'status' => ColorCommentStatus::Approved,
                'approved_body' => DB::raw('body'),
                'moderation_reason' => null,
                'moderated_at' => now(),
            ]
            : [
                'status' => ColorCommentStatus::Rejected,
                'moderation_reason' => $result->category->value ?? 'policy',
                'moderated_at' => now(),
            ];

        $applied = $this->apply($commentId, $revision, $attributes);

        if (! $applied) {
            return false;
        }

        if ($approved) {
            // Aviso so na aprovacao, e so uma vez por comentario: o
            // `notified_at` e reivindicado la dentro.
            ($this->notifyApproved)($commentId);
        } else {
            // Rejeitar encerra as denuncias pendentes: o texto deixou de ser
            // publicado, entao nao ha mais o que o admin decidir sobre ele.
            ($this->resolveReports)($commentId);
        }

        return true;
    }

    /**
     * Falha definitiva da moderacao. O comentario continua no ar como
     * `pending` ate o admin decidir.
     */
    public function flag(string $commentId, int $revision, string $reason): bool
    {
        return $this->apply($commentId, $revision, [
            'status' => ColorCommentStatus::Flagged,
            'moderation_reason' => $reason,
            'moderated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function apply(string $commentId, int $revision, array $attributes): bool
    {
        return ColorPaletteComment::query()
            ->whereKey($commentId)
            ->where('revision', $revision)
            ->where('status', ColorCommentStatus::Pending->value)
            ->update($attributes) > 0;
    }
}
