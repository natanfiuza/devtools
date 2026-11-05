<?php

namespace App\Models;

use Database\Factories\BadgeCustomLogoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Logo personalizado do BadgeForge (F3, ADR-078).
 *
 * `paths` e o SVG normalizado: uma lista de `{d, fillRule?, clipRule?}`. O
 * upload original nunca e persistido. A referencia publica e `u_<ULID>`, um
 * namespace reservado que o catalogo embutido nao usa.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $view_box
 * @property list<array{d: string, fillRule?: string, clipRule?: string}> $paths
 * @property int $path_count
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['id', 'user_id', 'name', 'view_box', 'paths', 'path_count', 'size_bytes'])]
class BadgeCustomLogo extends Model
{
    /** @use HasFactory<BadgeCustomLogoFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paths' => 'array',
            'path_count' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Referencia publica do logo (`?logo=u_<ULID>`).
     */
    public function slug(): string
    {
        return 'u_'.$this->getKey();
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
