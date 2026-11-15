<?php

namespace App\Actions\Colors;

use App\Enums\ColorCommentStatus;
use App\Jobs\ModerateColorComment;
use App\Models\ColorPaletteComment;
use App\Models\User;
use App\Support\Colors\Moderation\WordFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edita o proprio comentario e o devolve a moderacao (F7, ADR-139/ADR-140).
 *
 * `revision` sobe dentro da transacao: o job em voo da revisao anterior deixa
 * de aplicar, e o veredito novo so vale para o texto novo. `approved_body`
 * permanece intacto - e a versao que reaparece se a edicao for rejeitada.
 */
final class UpdateColorComment
{
    public function __construct(private readonly WordFilter $filter) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $author, ColorPaletteComment $comment, string $body): ColorPaletteComment
    {
        abort_unless(
            $comment->user_id !== null && (int) $comment->user_id === (int) $author->getKey(),
            404,
        );

        $body = trim($body);

        if ($this->filter->matches($body, $comment->locale)) {
            throw ValidationException::withMessages([
                'body' => __('colors.validation.comment_blocked'),
            ]);
        }

        $updated = DB::transaction(function () use ($comment, $body): ColorPaletteComment {
            $fresh = ColorPaletteComment::query()
                ->whereKey($comment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $fresh->forceFill([
                'body' => $body,
                'status' => ColorCommentStatus::Pending,
                'revision' => $fresh->revision + 1,
                'moderation_reason' => null,
                'moderated_at' => null,
                'edited_at' => now(),
            ])->save();

            return $fresh;
        });

        ModerateColorComment::dispatch($updated->id, $updated->revision);

        return $updated;
    }
}
