<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Paleta publica favoritada por um usuario (F5, ADR-128).
 *
 * O vinculo nao copia a paleta: a listagem sempre le a origem e so mostra o
 * que continua publico e ativo. A unicidade por conta impede duplicatas.
 *
 * @property string $id
 * @property int $user_id
 * @property string $color_palette_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read ColorPalette $palette
 */
#[Fillable(['id', 'user_id', 'color_palette_id'])]
class ColorPaletteFavorite extends Model
{
    use HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ColorPalette, $this>
     */
    public function palette(): BelongsTo
    {
        return $this->belongsTo(ColorPalette::class, 'color_palette_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}
