<?php

namespace App\Models;

use Database\Factories\BadgePresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Preset do BadgeForge (F3, ADR-079).
 *
 * `payload` e o snapshot do formulario (o mesmo `BadgeFormState` do cliente),
 * incluindo o logo escolhido. Nao e um badge publicado: nao tem URL publica.
 * Se o logo referenciado sumir, o preset e lido como orfao e a UI aplica o
 * formulario sem logo.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property array<string, mixed> $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['id', 'user_id', 'name', 'payload'])]
class BadgePreset extends Model
{
    /** @use HasFactory<BadgePresetFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
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
     * @param  Builder<static>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}
