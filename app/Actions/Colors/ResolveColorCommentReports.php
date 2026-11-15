<?php

namespace App\Actions\Colors;

use App\Enums\ColorCommentReportStatus;
use App\Models\ColorCommentReport;
use App\Models\User;

/**
 * Resolve as denuncias pendentes de um comentario (F7, ADR-141).
 *
 * Rejeitar ou excluir o comentario encerra as denuncias sem decisao propria do
 * admin sobre cada uma: o alvo deixou de existir como publicado. A resolucao
 * automatica nao grava `resolved_by`; a do admin grava.
 */
final class ResolveColorCommentReports
{
    public function __invoke(string $commentId, ?User $resolver = null): int
    {
        return ColorCommentReport::query()
            ->where('comment_id', $commentId)
            ->where('status', ColorCommentReportStatus::Pending->value)
            ->update([
                'status' => ColorCommentReportStatus::Resolved->value,
                'resolved_at' => now(),
                'resolved_by' => $resolver?->getKey(),
            ]);
    }
}
