<?php

namespace App\Actions\Colors;

use App\Models\ColorPaletteComment;
use App\Models\User;

/**
 * Exclui o proprio comentario (F7, ADR-139).
 *
 * Soft delete: o admin continua vendo o texto para auditoria. A raiz com
 * respostas vira tombstone; sem respostas, some da thread. Respostas excluidas
 * somem sempre. O retorno diz a tela qual dos dois casos aconteceu. Excluir
 * encerra as denuncias pendentes: o alvo deixou de existir publicamente.
 */
final class DeleteColorComment
{
    public function __construct(private readonly ResolveColorCommentReports $resolveReports) {}

    public function __invoke(User $author, ColorPaletteComment $comment): bool
    {
        abort_unless(
            $comment->user_id !== null && (int) $comment->user_id === (int) $author->getKey(),
            404,
        );

        $tombstone = $comment->replies()->whereNull('deleted_at')->exists();

        $comment->delete();

        ($this->resolveReports)($comment->getKey());

        return $tombstone;
    }
}
