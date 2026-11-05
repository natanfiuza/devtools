<?php

namespace App\Models;

use Database\Factories\BadgeCustomProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Provider JSON customizado de uma conta (F7, ADR-087).
 *
 * O template de origem e segredo de operacao: `Hidden` impede o vazamento por
 * `toArray()`/JSON, e as props do dono selecionam apenas colunas publicas. O
 * admin le o atributo diretamente para exibir a origem. A imagem publica e o
 * ULID; `enabled=false` faz a URL responder 404 sem apagar a configuracao.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $url_template
 * @property list<string> $path_params
 * @property string $example_path
 * @property int $metrics_version
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, BadgeCustomProviderDefinition> $definitions
 */
#[Fillable([
    'id', 'user_id', 'name', 'url_template', 'path_params', 'example_path',
    'metrics_version', 'enabled',
])]
#[Hidden(['url_template'])]
class BadgeCustomProvider extends Model
{
    /** @use HasFactory<BadgeCustomProviderFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'path_params' => 'array',
            'metrics_version' => 'integer',
            'enabled' => 'boolean',
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
     * @return HasMany<BadgeCustomProviderDefinition, $this>
     */
    public function definitions(): HasMany
    {
        return $this->hasMany(BadgeCustomProviderDefinition::class, 'provider_id')
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * @param  Builder<BadgeCustomProvider>  $query
     * @return Builder<BadgeCustomProvider>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    /**
     * Nomes dos placeholders do template, na ordem em que aparecem.
     *
     * @return list<string>
     */
    public function pathParamNames(): array
    {
        return $this->path_params;
    }

    public function publicUrl(string $definition, string $path, string $extension): string
    {
        $base = rtrim((string) config('badgeforge.custom_providers.path', '/api/badges/custom'), '/');

        return url($base.'/'.$this->getKey().'/'.$definition.'/'.$path.'.'.$extension);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }
}
