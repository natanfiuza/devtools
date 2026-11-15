<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Colors\ResolveColorCommentReport;
use App\Enums\AdminActionType;
use App\Enums\ColorCommentReportStatus;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\ColorCommentReport;
use App\Models\User;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Denuncias de comentario no painel (F7, ADR-141).
 *
 * O admin ve o comentario denunciado - inclusive excluido, que so aqui mantem
 * o texto - e decide entre resolver e dispensar. A decisao nunca altera o
 * comentario: rejeitar ou excluir e acao da moderacao (comentarios), e essas
 * acoes resolvem as denuncias pendentes automaticamente.
 */
final class CommentReportController extends Controller
{
    use RecordsAdminActions;

    private const array STATUSES = ['pending', 'resolved', 'dismissed', 'all'];

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $status = is_string($status) && in_array($status, self::STATUSES, true)
            ? $status
            : ColorCommentReportStatus::Pending->value;

        $reports = ColorCommentReport::query()
            ->with([
                'comment.palette:id,name,slug',
                'comment.author:id,name',
                'reporter:id,name,email',
                'resolver:id,name',
            ])
            ->when($status !== 'all', static fn (Builder $query): Builder => $query->where('status', $status))
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->paginate(max(1, (int) config('colors.reports.per_page', 30)))
            ->withQueryString()
            ->through(static fn (ColorCommentReport $report): array => [
                'id' => $report->id,
                'reason' => $report->reason->value,
                'status' => $report->status->value,
                'reportedAt' => Format::date($report->reported_at),
                'resolvedAt' => $report->resolved_at === null ? null : Format::date($report->resolved_at),
                'resolverName' => $report->resolver?->name,
                'reporterName' => $report->reporter?->name,
                'reporterEmail' => $report->reporter?->email,
                'comment' => [
                    'id' => $report->comment->id,
                    'body' => $report->comment->body,
                    'status' => $report->comment->status->value,
                    'deleted' => $report->comment->deleted_at !== null,
                    'paletteName' => $report->comment->palette->name,
                    'paletteSlug' => $report->comment->palette->slug,
                    'authorName' => $report->comment->author?->name,
                ],
            ]);

        return Inertia::render('admin/CommentReports', [
            'reports' => $reports,
            'filters' => ['status' => $status],
        ]);
    }

    public function resolve(
        Request $request,
        ColorCommentReport $commentReport,
        ResolveColorCommentReport $resolve,
    ): RedirectResponse {
        return $this->decide($request, $commentReport, ColorCommentReportStatus::Resolved, $resolve);
    }

    public function dismiss(
        Request $request,
        ColorCommentReport $commentReport,
        ResolveColorCommentReport $resolve,
    ): RedirectResponse {
        return $this->decide($request, $commentReport, ColorCommentReportStatus::Dismissed, $resolve);
    }

    private function decide(
        Request $request,
        ColorCommentReport $report,
        ColorCommentReportStatus $status,
        ResolveColorCommentReport $resolve,
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        $from = $report->status;

        $resolve($actor, $report, $status);

        // `admin_actions.target_id` e inteiro; denuncia e comentario sao ULID.
        // Os ids vao no meta curto, junto com o estado anterior e o novo.
        $this->record(
            $request,
            $status === ColorCommentReportStatus::Resolved
                ? AdminActionType::CommentReportResolved
                : AdminActionType::CommentReportDismissed,
            null,
            [
                'id' => $report->id,
                'comment_id' => $report->comment_id,
                'from' => $from->value,
                'to' => $status->value,
            ],
        );

        return $this->back(
            'success',
            $status === ColorCommentReportStatus::Resolved
                ? __('admin.toasts.comment_report_resolved')
                : __('admin.toasts.comment_report_dismissed'),
        );
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('admin.comment-reports.index');
    }
}
