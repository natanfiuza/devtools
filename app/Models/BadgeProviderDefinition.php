<?php

namespace App\Models;

use Database\Factories\BadgeProviderDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Definicao de metrica de um provider (F4, ADR-080).
 *
 * `required_params` e uma lista de `{name, pattern, allowSlash, placeholderKey}`.
 * O texto do placeholder nunca vem do banco: a UI traduz `placeholderKey`.
 *
 * @property int $id
 * @property int $provider_id
 * @property string $slug
 * @property string $name
 * @property string $label
 * @property string $client_key
 * @property string $extractor_key
 * @property string $path_pattern
 * @property list<array{name: string, pattern: string, allowSlash: bool, placeholderKey: string}> $required_params
 * @property array<string, mixed>|null $optional_params
 * @property string $url_template
 * @property string $response_type
 * @property string|null $parser_class
 * @property bool $supports_private
 * @property string $example_path
 * @property int $sort
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BadgeProvider $provider
 */
#[Fillable([
    'provider_id', 'slug', 'name', 'label', 'client_key', 'extractor_key',
    'path_pattern', 'required_params', 'optional_params', 'url_template',
    'response_type', 'parser_class', 'supports_private', 'example_path',
    'sort', 'enabled',
])]
class BadgeProviderDefinition extends Model
{
    /** @use HasFactory<BadgeProviderDefinitionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_params' => 'array',
            'optional_params' => 'array',
            'supports_private' => 'boolean',
            'sort' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BadgeProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(BadgeProvider::class, 'provider_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForProvider(Builder $query, int $providerId): void
    {
        $query->where('provider_id', $providerId);
    }
}
