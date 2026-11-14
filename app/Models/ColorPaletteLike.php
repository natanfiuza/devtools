<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Curtida de uma paleta publica (F6, ADR-135).
 *
 * O ator e a conta ou o identificador anonimo, nunca os dois. A linha guarda
 * o estado (`liked_at`) em vez de ser apagada, para que o aviso ao dono nao se
 * repita ao descurtir e curtir de novo.
 *
 * @property string $id
 * @property string $color_palette_id
 * @property int|null $user_id
 * @property string|null $anonymous_id
 * @property Carbon|null $liked_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ColorPalette $palette
 * @property-read User|null $user
 */
#[Fillable([
    'id',
    'color_palette_id',
    'user_id',
    'anonymous_id',
    'liked_at',
    'notified_at',
])]
class ColorPaletteLike extends Model
{
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'liked_at' => 'datetime',
            'notified_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Somente linhas que ainda representam uma curtida ativa.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('liked_at');
    }

    public function isActive(): bool
    {
        return $this->liked_at !== null;
    }
}
