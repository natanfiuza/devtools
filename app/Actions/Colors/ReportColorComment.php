<?php

namespace App\Actions\Colors;

use App\Enums\ColorCommentReportReason;
use App\Enums\ColorCommentReportStatus;
use App\Models\ColorCommentReport;
use App\Models\ColorPaletteComment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Denuncia um comentario (F7, ADR-141).
 *
 * Uma linha por par comentario/denunciante. Repetir uma denuncia pendente ou
 * ja resolvida nao cria outra e responde 422; uma denuncia dispensada volta a
 * pendente na mesma linha, com motivo e data novos. O proprio autor nunca
 * denuncia o proprio comentario.
 */
final class ReportColorComment
{
    /**
     * @throws ValidationException
     */
    public function __invoke(
        User $reporter,
        ColorPaletteComment $comment,
        ColorCommentReportReason $reason,
    ): ColorCommentReport {
        abort_unless($comment->isVisibleToPublic(), 404);

        if ($comment->user_id !== null && (int) $comment->user_id === (int) $reporter->getKey()) {
            throw ValidationException::withMessages([
                'reason' => __('colors.validation.report_own'),
            ]);
        }

        try {
            return DB::transaction(
                fn (): ColorCommentReport => $this->persist($reporter, $comment, $reason),
            );
        } catch (UniqueConstraintViolationException) {
            // Corrida entre duas abas: o unique decide quem grava primeiro.
            throw ValidationException::withMessages([
                'reason' => __('colors.validation.report_duplicate'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function persist(
        User $reporter,
        ColorPaletteComment $comment,
        ColorCommentReportReason $reason,
    ): ColorCommentReport {
        $existing = ColorCommentReport::query()
            ->where('comment_id', $comment->getKey())
            ->where('user_id', $reporter->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            return ColorCommentReport::query()->create([
                'comment_id' => $comment->getKey(),
                'user_id' => $reporter->getKey(),
                'reason' => $reason,
                'status' => ColorCommentReportStatus::Pending,
                'reported_at' => now(),
            ]);
        }

        if ($existing->status !== ColorCommentReportStatus::Dismissed) {
            throw ValidationException::withMessages([
                'reason' => __('colors.validation.report_duplicate'),
            ]);
        }

        $existing->forceFill([
            'status' => ColorCommentReportStatus::Pending,
            'reason' => $reason,
            'reported_at' => now(),
            'resolved_at' => null,
            'resolved_by' => null,
        ])->save();

        return $existing;
    }
}
