<?php

namespace App\Models;

use Database\Factories\ColorPaletteMuteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paleta silenciada pelo dono (F7, ADR-142).
 *
 * O vinculo e idempotente e so o dono pode criar. O envio consulta este par
 * no momento da entrega, entao desligar o mute volta a permitir avisos das
 * proximas interacoes, sem reenviar o que ja passou.
 *
 * @property int $id
 * @property string $color_palette_id
 * @property int $user_id
 * @property-read ColorPalette $palette
 * @property-read User $user
 */
#[Fillable(['color_palette_id', 'user_id'])]
class ColorPaletteMute extends Model
{
    /** @use HasFactory<ColorPaletteMuteFactory> */
    use HasFactory;

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

    public static function isMuted(User $user, string $paletteId): bool
    {
        return self::query()
            ->where('user_id', $user->getKey())
            ->where('color_palette_id', $paletteId)
            ->exists();
    }
}
