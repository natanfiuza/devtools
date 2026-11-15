<?php

namespace App\Support\Colors;

use App\Enums\ColorCommentStatus;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shapes da thread de comentarios (F7, ADR-139).
 *
 * Nada de `user_id`, e-mail, `revision`, `approved_body` ou motivo de
 * moderacao sai daqui: o publico recebe o corpo ja decidido por
 * `displayBody()`, o selo de "em analise" e os direitos do visitante. Raiz
 * excluida com respostas vira tombstone; resposta excluida some.
 */
final class CommentPresenter
{
    /**
     * @param  list<array<string, mixed>>  $replies
     * @return array<string, mixed>
     */
    public function item(
        ColorPaletteComment $comment,
        ?User $viewer,
        array $replies = [],
        bool $repliesTruncated = false,
    ): array {
        $deleted = $comment->deleted_at !== null;
        $author = $this->author($comment);
        $isOwn = $viewer instanceof User
            && $comment->user_id !== null
            && (int) $comment->user_id === (int) $viewer->getKey();

        return [
            'id' => $comment->id,
            // Tombstone nao carrega texto: o corpo vira string vazia para o
            // cliente nao lidar com dois tipos no mesmo campo.
            'body' => $comment->displayBody() ?? '',
            'pending' => $comment->awaitsReview(),
            'deleted' => $deleted,
            'created_at' => $comment->created_at?->toIso8601String(),
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'author' => $author,
            'author_removed' => $author === null,
            'can_edit' => $isOwn && ! $deleted,
            'can_delete' => $isOwn && ! $deleted,
            'can_report' => $viewer instanceof User
                && ! $deleted
                && ! $isOwn
                && $comment->isVisibleToPublic(),
            'replies' => $replies,
            'replies_truncated' => $repliesTruncated,
        ];
    }

    /**
     * Raizes paginadas (mais novas primeiro) com as respostas ja agrupadas.
     *
     * As respostas sao buscadas em uma unica consulta para a pagina inteira e
     * fatiadas pelo limite configurado; a consulta de raizes so inclui tombstone
     * quando ainda existe resposta visivel.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function thread(ColorPalette $palette, ?User $viewer, int $page): LengthAwarePaginator
    {
        $perPage = max(1, (int) config('colors.comments.per_page', 20));

        // `withTrashed` e necessario so para a raiz virar tombstone: o filtro
        // logo abaixo garante que excluida sem resposta viva nao aparece.
        $roots = ColorPaletteComment::query()
            ->withTrashed()
            ->forPalette($palette->id)
            ->roots()
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    // Visivel: nao excluida e com texto exibivel.
                    $query->whereNull('deleted_at')
                        ->where(function (Builder $query): void {
                            $query->where('status', '!=', ColorCommentStatus::Rejected->value)
                                ->orWhereNotNull('approved_body');
                        });
                })->orWhere(function (Builder $query): void {
                    // Tombstone: excluida, mas a conversa continua.
                    $query->whereNotNull('deleted_at')
                        ->whereHas('replies', static fn (Builder $replies): Builder => $replies->whereNull('deleted_at'));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'comments_page', max(1, $page));

        $ids = array_values(array_map(
            static fn (ColorPaletteComment $comment): string => $comment->id,
            $roots->items(),
        ));

        $repliesByRoot = $this->visibleReplies($ids);
        $limit = max(0, (int) config('colors.comments.replies_limit', 100));

        $roots->through(function (ColorPaletteComment $root) use ($viewer, $repliesByRoot, $limit): array {
            $all = $repliesByRoot[$root->id] ?? [];
            $shown = array_slice($all, 0, $limit);

            $replies = array_map(
                fn (ColorPaletteComment $reply): array => $this->item($reply, $viewer),
                $shown,
            );

            return $this->item($root, $viewer, $replies, count($all) > count($shown));
        });

        return $roots;
    }

    /**
     * Respostas visiveis da pagina inteira, agrupadas por raiz.
     *
     * @param  list<string>  $rootIds
     * @return array<string, list<ColorPaletteComment>>
     */
    private function visibleReplies(array $rootIds): array
    {
        if ($rootIds === []) {
            return [];
        }

        $replies = ColorPaletteComment::query()
            ->whereIn('parent_id', $rootIds)
            ->whereNull('deleted_at')
            ->where(function (Builder $query): void {
                $query->where('status', '!=', ColorCommentStatus::Rejected->value)
                    ->orWhereNotNull('approved_body');
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $grouped = [];

        foreach ($replies as $reply) {
            $grouped[(string) $reply->parent_id][] = $reply;
        }

        return $grouped;
    }

    /**
     * Conta removida, suspensa ou anonimizada vira "conta removida"; o vinculo
     * com o perfil publico so existe quando ha username utilizavel.
     *
     * @return array{id: int, name: string, username: string|null, avatar: string|null}|null
     */
    private function author(ColorPaletteComment $comment): ?array
    {
        $author = $comment->author;

        if (! $author instanceof User
            || $author->trashed()
            || $author->isSuspended()
            || $author->isAnonymized()) {
            return null;
        }

        return [
            'id' => (int) $author->getKey(),
            'name' => $author->name,
            'username' => $author->username,
            'avatar' => ProfilePresenter::avatar($author),
        ];
    }
}
