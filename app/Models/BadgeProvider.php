<?php

namespace App\Models;

use Database\Factories\BadgeProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Provider externo do BadgeForge (F4, ADR-080).
 *
 * O metadado e editavel no banco, mas o codigo nunca instancia uma classe
 * vinda daqui: `client_key` da definicao e resolvido pelo registry. `enabled`
 * desliga a rota publica e some da documentacao sem apagar historico.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $category
 * @property bool $enabled
 * @property bool $requires_auth
 * @property string|null $auth_type
 * @property string|null $base_url
 * @property int|null $cache_ttl
 * @property string|null $documentation_url
 * @property int|null $rate_limit_per_minute
 * @property array<string, mixed>|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'slug', 'name', 'category', 'enabled', 'requires_auth', 'auth_type',
    'base_url', 'cache_ttl', 'documentation_url', 'rate_limit_per_minute',
    'config',
])]
class BadgeProvider extends Model
{
    /** @use HasFactory<BadgeProviderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'requires_auth' => 'boolean',
            'cache_ttl' => 'integer',
            'rate_limit_per_minute' => 'integer',
            'config' => 'array',
        ];
    }

    /**
     * @return HasMany<BadgeProviderDefinition, $this>
     */
    public function definitions(): HasMany
    {
        return $this->hasMany(BadgeProviderDefinition::class, 'provider_id');
    }

    /**
     * TTL efetivo do cache: o override do provider ou o default da config.
     */
    public function effectiveCacheTtl(): int
    {
        return $this->cache_ttl ?? (int) config('badgeforge.providers.cache_ttl', 300);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }
}
