<?php

namespace App\Models;

use App\Enums\ColorCommentStatus;
use Database\Factories\ColorPaletteCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Comentario de paleta publica (F7, ADR-139/ADR-140).
 *
 * A thread tem no maximo dois niveis: `parent_id` so aponta para uma raiz.
 * `body` e a revisao atual, moderada de forma assincrona; `approved_body`
 * guarda a ultima versao aprovada e e o que reaparece quando uma edicao e
 * rejeitada. `revision` invalida resultados de moderacao de revisoes antigas.
 *
 * Excluir e soft delete: a raiz com respostas vira tombstone e os filhos
 * deixam de aparecer. O texto continua no banco para o admin e para o autor
 * "conta removida" preservar a conversa.
 *
 * @property string $id
 * @property string $color_palette_id
 * @property string|null $parent_id
 * @property int|null $user_id
 * @property string $body
 * @property string|null $approved_body
 * @property ColorCommentStatus $status
 * @property string $locale
 * @property int $revision
 * @property string|null $moderation_reason
 * @property Carbon|null $moderated_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ColorPalette $palette
 * @property-read User|null $author
 * @property-read ColorPaletteComment|null $parent
 * @property-read Collection<int, ColorPaletteComment> $replies
 * @property-read Collection<int, ColorCommentReport> $reports
 */
#[Fillable([
    'id',
    'color_palette_id',
    'parent_id',
    'user_id',
    'body',
    'approved_body',
    'status',
    'locale',
    'revision',
    'moderation_reason',
    'moderated_at',
    'notified_at',
    'edited_at',
])]
class ColorPaletteComment extends Model
{
    /** @use HasFactory<ColorPaletteCommentFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ColorCommentStatus::class,
            'revision' => 'integer',
            'moderated_at' => 'datetime',
            'notified_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ColorPalette, $this>
     */
    public function palette(): BelongsTo
    {
        return $this->belongsTo(ColorPalette::class, 'color_palette_id');
    }

    /**
     * A conta que escreveu. `withTrashed` mantem o nome disponivel enquanto a
     * conta esta na carencia; anonimizada aparece como "conta removida" no
     * presenter, e o expurgo definitivo deixa `user_id` NULL.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<ColorPaletteComment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    /**
     * @return HasMany<ColorPaletteComment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ColorCommentReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ColorCommentReport::class, 'comment_id');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * O texto que o publico pode ver: a revisao atual quando nao ha rejeicao,
     * a ultima aprovada quando a revisao atual foi rejeitada, ou nada quando
     * nunca houve aprovacao ou o comentario foi excluido.
     */
    public function displayBody(): ?string
    {
        if ($this->deleted_at !== null) {
            return null;
        }

        if ($this->status === ColorCommentStatus::Rejected) {
            return $this->approved_body;
        }

        return $this->body;
    }

    /**
     * `pending` e `flagged` sao visiveis com selo; `rejected` so quando existe
     * texto aprovado anterior; excluido nunca e visivel como corpo.
     */
    public function isVisibleToPublic(): bool
    {
        if ($this->deleted_at !== null) {
            return false;
        }

        if ($this->status === ColorCommentStatus::Rejected) {
            return $this->approved_body !== null;
        }

        return true;
    }

    public function awaitsReview(): bool
    {
        return $this->status->awaitsReview();
    }

    /**
     * Só uma raiz visivel aceita resposta; responder a uma resposta e recusado
     * na action para manter a thread em dois niveis (ADR-111).
     */
    public function canBeRepliedTo(): bool
    {
        return $this->isRoot() && $this->isVisibleToPublic();
    }

    public function hasReplies(): bool
    {
        return $this->replies()->exists();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForPalette(Builder $query, string $paletteId): void
    {
        $query->where('color_palette_id', $paletteId);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleToPublic(Builder $query): void
    {
        $query->whereNull('deleted_at')
            ->where(function (Builder $query): void {
                $query->where('status', '!=', ColorCommentStatus::Rejected->value)
                    ->orWhereNotNull('approved_body');
            });
    }
}
