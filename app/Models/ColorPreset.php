<?php

namespace App\Models;

use Database\Factories\ColorPresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Preset do Color Palette Generator (F5, ADR-093).
 *
 * Guarda a entrada da geracao (nome + cor base), nunca as cores derivadas:
 * aplicar o preset recalcula a escala pelo algoritmo vigente. Nao e paleta:
 * nao tem URL publica, cota, uso nem aparencia em Minhas Paletas.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $base
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['id', 'user_id', 'name', 'base'])]
class ColorPreset extends Model
{
    /** @use HasFactory<ColorPresetFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}
