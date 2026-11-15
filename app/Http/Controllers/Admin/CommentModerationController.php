<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Colors\AdminModerateColorComment;
use App\Enums\AdminActionType;
use App\Enums\ColorCommentStatus;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\ColorPaletteComment;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Moderacao de comentarios no painel (F7, ADR-139/ADR-140).
 *
 * A fila mostra o texto integral - e o que o admin precisa para decidir -, mas
 * esse texto nunca vai para log nem para a auditoria: o `admin_actions` guarda
 * so ids e o estado anterior/novo. O comentario excluido continua aqui para
 * consulta; aprovar ou rejeitar sobre ele nao faz nada.
 */
final class CommentModerationController extends Controller
{
    use RecordsAdminActions;

    private const array STATUSES = ['pending', 'flagged', 'rejected', 'approved', 'all'];

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $status = is_string($status) && in_array($status, self::STATUSES, true)
            ? $status
            : ColorCommentStatus::Pending->value;

        $search = $request->string('q')->trim()->value();

        $comments = ColorPaletteComment::query()
            ->withTrashed()
            ->with(['palette:id,name,slug', 'author:id,name,username'])
            ->withCount([
                'reports as pending_reports_count' => static fn (Builder $query): Builder => $query->where('status', 'pending'),
            ])
            ->when($status !== 'all', static fn (Builder $query): Builder => $query->where('status', $status))
            ->when($search !== '', static fn (Builder $query): Builder => $query->where('body', 'like', '%'.$search.'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, (int) config('colors.comments.per_page', 20)))
            ->withQueryString()
            ->through(static fn (ColorPaletteComment $comment): array => [
                'id' => $comment->id,
                'body' => $comment->body,
                'approvedBody' => $comment->approved_body,
                'status' => $comment->status->value,
                'deleted' => $comment->deleted_at !== null,
                'paletteName' => $comment->palette->name,
                'paletteSlug' => $comment->palette->slug,
                'authorName' => $comment->author?->name,
                'authorUsername' => $comment->author?->username,
                'createdAt' => Format::dateTime($comment->created_at),
                // O `withCount` nao vira propriedade tipada; le-se o atributo
                // cru, como em ProfilePresenter::likesCount().
                'pendingReports' => (int) ($comment->getAttributes()['pending_reports_count'] ?? 0),
            ]);

        return Inertia::render('admin/CommentModeration', [
            'comments' => $comments,
            'filters' => ['status' => $status, 'q' => $search],
        ]);
    }

    public function approve(
        Request $request,
        string $comment,
        AdminModerateColorComment $moderate,
    ): RedirectResponse {
        return $this->decide($request, $comment, AdminActionType::CommentApproved, __('admin.toasts.comment_approved'), $moderate->approve(...));
    }

    public function reject(
        Request $request,
        string $comment,
        AdminModerateColorComment $moderate,
    ): RedirectResponse {
        return $this->decide($request, $comment, AdminActionType::CommentRejected, __('admin.toasts.comment_rejected'), $moderate->reject(...));
    }

    public function destroy(
        Request $request,
        string $comment,
        AdminModerateColorComment $moderate,
    ): RedirectResponse {
        return $this->decide($request, $comment, AdminActionType::CommentDeleted, __('admin.toasts.comment_deleted'), $moderate->delete(...));
    }

    /**
     * @param  callable(ColorPaletteComment): bool  $act
     */
    private function decide(
        Request $request,
        string $commentId,
        AdminActionType $action,
        string $toast,
        callable $act,
    ): RedirectResponse {
        // O admin consulta o excluido; a acao sobre ele simplesmente nao muda
        // nada e nao inventa auditoria.
        $comment = ColorPaletteComment::query()->withTrashed()->findOrFail($commentId);

        if (! $act($comment)) {
            return $this->back($toast);
        }

        // `admin_actions.target_id` e inteiro; comentario e ULID. Os ids vao no
        // meta, junto com o estado anterior e o novo (nunca o texto).
        $this->record($request, $action, null, [
            'id' => (string) $comment->getKey(),
            'palette_id' => $comment->color_palette_id,
            'from' => $comment->status->value,
            'to' => $action === AdminActionType::CommentDeleted
                ? 'deleted'
                : ($action === AdminActionType::CommentApproved ? 'approved' : 'rejected'),
        ]);

        return $this->back($toast);
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('admin.comments.index');
    }
}
