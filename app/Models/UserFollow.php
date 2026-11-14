<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vinculo de seguir entre duas contas (F6, ADR-136).
 *
 * A linha e permanente: `followed_at` nulo e o estado inativo e o vinculo usa
 * o id da conta, nao o username. `notified_at` evita avisar duas vezes a mesma
 * relacao.
 *
 * @property string $id
 * @property int $follower_id
 * @property int $followed_id
 * @property Carbon|null $followed_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $follower
 * @property-read User $followed
 */
#[Fillable(['id', 'follower_id', 'followed_id', 'followed_at', 'notified_at'])]
class UserFollow extends Model
{
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'followed_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function followed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('followed_at');
    }

    public function isActive(): bool
    {
        return $this->followed_at !== null;
    }
}
