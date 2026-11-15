<?php

namespace App\Actions\Colors;

use App\Enums\ColorCommentReportStatus;
use App\Models\ColorCommentReport;
use App\Models\User;

/**
 * Decisao do admin sobre uma denuncia (F7, ADR-141).
 *
 * Resolver aceita a denuncia; dispensar a encerra sem acao sobre o comentario.
 * O denunciante pode reabrir uma dispensada; a resolvida nao reabre. Quem
 * registra a auditoria e o controlador, porque o meta depende da acao.
 */
final class ResolveColorCommentReport
{
    public function __invoke(
        User $admin,
        ColorCommentReport $report,
        ColorCommentReportStatus $status,
    ): ColorCommentReport {
        abort_unless(
            $status === ColorCommentReportStatus::Resolved
                || $status === ColorCommentReportStatus::Dismissed,
            404,
        );

        $report->forceFill([
            'status' => $status,
            'resolved_at' => now(),
            'resolved_by' => $admin->getKey(),
        ])->save();

        return $report;
    }
}
