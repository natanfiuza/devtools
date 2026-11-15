<?php

namespace App\Http\Controllers\Api\Colors;

use App\Actions\Colors\CreateColorComment;
use App\Actions\Colors\DeleteColorComment;
use App\Actions\Colors\UpdateColorComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\StoreColorCommentRequest;
use App\Http\Requests\Colors\UpdateColorCommentRequest;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use App\Support\Colors\CommentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Escrita da thread de comentarios (F7, ADR-139).
 *
 * Todas as rotas ficam atras do login e do interruptor `colors.comments`.
 * Paleta nao publica, comentario de outra conta e revisao oculta respondem o
 * mesmo 404 da pagina publica; o autor nunca e revelado por um 403.
 */
class ColorCommentController extends Controller
{
    public function store(
        StoreColorCommentRequest $request,
        ColorPalette $colorPalette,
        CreateColorComment $create,
        CommentPresenter $presenter,
    ): JsonResponse {
        $user = $this->account($request);
        $this->authorizePalette($colorPalette);

        /** @var array{body: string, parent_id?: string|null} $data */
        $data = $request->validated();

        $comment = $create(
            $user,
            $colorPalette,
            $data['body'],
            $data['parent_id'] ?? null,
            app()->getLocale(),
        );

        return response()->json([
            'comment' => $presenter->item($comment, $user),
        ], 201);
    }

    public function update(
        UpdateColorCommentRequest $request,
        ColorPaletteComment $colorPaletteComment,
        UpdateColorComment $update,
        CommentPresenter $presenter,
    ): JsonResponse {
        $user = $this->account($request);
        $this->authorizeComment($colorPaletteComment);

        /** @var array{body: string} $data */
        $data = $request->validated();

        $comment = $update($user, $colorPaletteComment, $data['body']);

        return response()->json([
            'comment' => $presenter->item($comment, $user),
        ]);
    }

    public function destroy(
        Request $request,
        ColorPaletteComment $colorPaletteComment,
        DeleteColorComment $delete,
        CommentPresenter $presenter,
    ): JsonResponse {
        $user = $this->account($request);
        $this->authorizeComment($colorPaletteComment);

        $tombstone = $delete($user, $colorPaletteComment);

        return response()->json([
            'deleted' => true,
            'tombstone' => $tombstone,
            'comment' => $tombstone
                ? $presenter->item($colorPaletteComment->refresh(), $user)
                : null,
        ]);
    }

    private function account(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function authorizePalette(ColorPalette $palette): void
    {
        abort_unless(
            $palette->isPublic() && ! $palette->isArchived() && $palette->slug !== null,
            404,
        );
    }

    private function authorizeComment(ColorPaletteComment $comment): void
    {
        $this->authorizePalette($comment->palette);

        abort_unless($comment->isVisibleToPublic() || $comment->deleted_at !== null, 404);
    }
}
