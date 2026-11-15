<?php

namespace App\Actions\Colors;

use App\Enums\ColorCommentStatus;
use App\Models\ColorPaletteComment;
use Illuminate\Support\Facades\DB;

/**
 * Decisao do admin sobre um comentario (F7, ADR-139/ADR-140).
 *
 * O admin aprova, rejeita ou exclui (tombstone). Toda decisao incrementa
 * `revision`, entao um resultado de moderacao que ainda estava em voo nao se
 * aplica por cima dela. Aprovar avisa os interessados pela mesma acao da
 * moderacao; rejeitar e excluir encerram as denuncias pendentes.
 */
final class AdminModerateColorComment
{
    public function __construct(
        private readonly ResolveColorCommentReports $resolveReports,
        private readonly NotifyApprovedComment $notifyApproved,
    ) {}

    public function approve(ColorPaletteComment $comment): bool
    {
        if ($comment->deleted_at !== null) {
            return false;
        }

        $applied = ColorPaletteComment::query()
            ->whereKey($comment->getKey())
            ->where('status', '!=', ColorCommentStatus::Approved->value)
            ->update([
                'status' => ColorCommentStatus::Approved->value,
                'approved_body' => DB::raw('body'),
                'moderation_reason' => null,
                'moderated_at' => now(),
                'revision' => DB::raw('revision + 1'),
            ]) > 0;

        if ($applied) {
            ($this->notifyApproved)((string) $comment->getKey());
        }

        return $applied;
    }

    public function reject(ColorPaletteComment $comment): bool
    {
        if ($comment->deleted_at !== null) {
            return false;
        }

        $applied = ColorPaletteComment::query()
            ->whereKey($comment->getKey())
            ->where('status', '!=', ColorCommentStatus::Rejected->value)
            ->update([
                'status' => ColorCommentStatus::Rejected->value,
                'moderation_reason' => 'policy',
                'moderated_at' => now(),
                'revision' => DB::raw('revision + 1'),
            ]) > 0;

        if ($applied) {
            ($this->resolveReports)((string) $comment->getKey());
        }

        return $applied;
    }

    public function delete(ColorPaletteComment $comment): bool
    {
        if ($comment->deleted_at !== null) {
            return false;
        }

        $id = (string) $comment->getKey();

        ColorPaletteComment::query()
            ->whereKey($id)
            ->update(['revision' => DB::raw('revision + 1')]);

        $comment->delete();

        ($this->resolveReports)($id);

        return true;
    }
}
