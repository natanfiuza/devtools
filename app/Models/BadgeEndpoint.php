<?php

namespace App\Models;

use Database\Factories\BadgeEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Endpoint JSON do BadgeForge (F5, ADR-083).
 *
 * A URL de origem fica no servidor: `Hidden` impede que ela vaze por
 * `toArray()`/JSON, e as props selecionam apenas as colunas publicas. A
 * imagem publica e identificada pelo ULID, consumida por `<img>` sem sessao.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['id', 'user_id', 'name', 'url'])]
#[Hidden(['url'])]
class BadgeEndpoint extends Model
{
    /** @use HasFactory<BadgeEndpointFactory> */
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
     * @param  Builder<BadgeEndpoint>  $query
     * @return Builder<BadgeEndpoint>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    public function publicSvgUrl(): string
    {
        return $this->publicUrl('svg');
    }

    public function publicPngUrl(): string
    {
        return $this->publicUrl('png');
    }

    public function publicUrl(string $extension): string
    {
        $base = rtrim((string) config('badgeforge.endpoint.path', '/api/badges/endpoint'), '/');

        return url($base.'/'.$this->getKey().'.'.$extension);
    }
}
