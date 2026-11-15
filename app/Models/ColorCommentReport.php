<?php

namespace App\Models;

use App\Enums\ColorCommentReportReason;
use App\Enums\ColorCommentReportStatus;
use Database\Factories\ColorCommentReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Denuncia de um comentario (F7, ADR-141).
 *
 * O par comentario/denunciante e unico. A linha sobrevive ao comentario
 * excluido? Nao: excluir o comentario leva as denuncias junto (`cascade`),
 * porque o alvo deixou de existir. Rejeitar ou excluir resolve as denuncias
 * pendentes antes da remocao.
 *
 * @property string $id
 * @property string $comment_id
 * @property int|null $user_id
 * @property int|null $resolved_by
 * @property ColorCommentReportReason $reason
 * @property ColorCommentReportStatus $status
 * @property Carbon $reported_at
 * @property Carbon|null $resolved_at
 * @property-read ColorPaletteComment $comment
 * @property-read User|null $reporter
 * @property-read User|null $resolver
 */
#[Fillable([
    'id',
    'comment_id',
    'user_id',
    'resolved_by',
    'reason',
    'status',
    'reported_at',
    'resolved_at',
])]
class ColorCommentReport extends Model
{
    /** @use HasFactory<ColorCommentReportFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ColorCommentReportReason::class,
            'status' => ColorCommentReportStatus::class,
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ColorPaletteComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(ColorPaletteComment::class, 'comment_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }
}
