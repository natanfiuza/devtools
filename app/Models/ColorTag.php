<?php

namespace App\Models;

use Database\Factories\ColorTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Tag de paleta de um usuario (F4, ADR-103).
 *
 * `key` e a forma normalizada (minuscula e espacos colapsados) usada para
 * impedir duplicatas dentro da conta. Renomear a tag alcanca todas as
 * paletas vinculadas; a ordem e a `position` global do usuario.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $key
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['id', 'user_id', 'name', 'key', 'position'])]
class ColorTag extends Model
{
    /** @use HasFactory<ColorTagFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<ColorPalette, $this>
     */
    public function palettes(): BelongsToMany
    {
        return $this->belongsToMany(ColorPalette::class, 'color_palette_color_tag');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}
