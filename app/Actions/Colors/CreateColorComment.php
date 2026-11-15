<?php

namespace App\Actions\Colors;

use App\Enums\ColorCommentStatus;
use App\Jobs\ModerateColorComment;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use App\Support\Colors\Moderation\WordFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria um comentario ou uma resposta (F7, ADR-139/ADR-140).
 *
 * A thread tem dois niveis: o pai so pode ser uma raiz visivel da mesma
 * paleta. O filtro local barra antes de gravar; o comentario nasce `pending` e
 * a moderacao roda depois do commit. Nada e publicado como aprovado antes do
 * veredito.
 */
final class CreateColorComment
{
    public function __construct(private readonly WordFilter $filter) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(
        User $author,
        ColorPalette $palette,
        string $body,
        ?string $parentId,
        string $locale,
    ): ColorPaletteComment {
        $body = trim($body);

        if ($this->filter->matches($body, $locale)) {
            throw ValidationException::withMessages([
                'body' => __('colors.validation.comment_blocked'),
            ]);
        }

        $parent = $this->parent($palette, $parentId);

        $comment = DB::transaction(static fn (): ColorPaletteComment => ColorPaletteComment::query()->create([
            'color_palette_id' => $palette->getKey(),
            'parent_id' => $parent?->getKey(),
            'user_id' => $author->getKey(),
            'body' => $body,
            'approved_body' => null,
            'status' => ColorCommentStatus::Pending,
            'locale' => $locale,
            'revision' => 1,
        ]));

        ModerateColorComment::dispatch($comment->id, $comment->revision);

        return $comment;
    }

    /**
     * @throws ValidationException
     */
    private function parent(ColorPalette $palette, ?string $parentId): ?ColorPaletteComment
    {
        if ($parentId === null) {
            return null;
        }

        $parent = ColorPaletteComment::query()
            ->whereKey($parentId)
            ->where('color_palette_id', $palette->getKey())
            ->first();

        if ($parent === null || ! $parent->canBeRepliedTo()) {
            throw ValidationException::withMessages([
                'parent_id' => __('colors.validation.comment_parent_invalid'),
            ]);
        }

        return $parent;
    }
}
