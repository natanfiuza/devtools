<?php

namespace App\Models;

use App\Enums\PaletteVisibility;
use Database\Factories\LayoutSystemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sistema de layout persistido de um usuario (F4, ADR-168).
 *
 * `state` e o retrato efetivo do workspace: snapshot da F3, entradas cruas e
 * secundaria manual. Reabrir nao recalcula nada. As colunas de versao e
 * `tokens_count` sao derivadas do snapshot validado no servidor. `slug` so
 * nasce na primeira publicacao e nunca muda ao renomear; arquivar move o valor
 * para `archived_slug`, restaurar devolve, excluir aposenta para sempre
 * (ADR-171). `source` congela o credito da paleta de origem (ADR-172).
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $duplicated_from_id
 * @property string|null $source_palette_id
 * @property string $name
 * @property string|null $description
 * @property PaletteVisibility $visibility
 * @property array<string, mixed> $state
 * @property array<string, mixed>|null $source
 * @property int $schema_version
 * @property int $algorithm_version
 * @property int $suggestions_version
 * @property int $snapshot_version
 * @property int $tokens_count
 * @property int $duplications_count
 * @property string|null $slug
 * @property string|null $archived_slug
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read LayoutSystem|null $duplicatedFrom
 * @property-read ColorPalette|null $sourcePalette
 */
#[Fillable([
    'id',
    'user_id',
    'duplicated_from_id',
    'source_palette_id',
    'name',
    'description',
    'visibility',
    'state',
    'source',
    'schema_version',
    'algorithm_version',
    'suggestions_version',
    'snapshot_version',
    'tokens_count',
    'duplications_count',
    'slug',
    'archived_slug',
    'archived_at',
])]
class LayoutSystem extends Model
{
    /** @use HasFactory<LayoutSystemFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => PaletteVisibility::class,
            'state' => 'array',
            'source' => 'array',
            'schema_version' => 'integer',
            'algorithm_version' => 'integer',
            'suggestions_version' => 'integer',
            'snapshot_version' => 'integer',
            'tokens_count' => 'integer',
            'duplications_count' => 'integer',
            'archived_at' => 'datetime',
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
     * @return BelongsToMany<ColorTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ColorTag::class, 'color_tag_layout_system');
    }

    /**
     * @return BelongsTo<LayoutSystem, $this>
     */
    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_id');
    }

    /**
     * @return HasMany<LayoutSystem, $this>
     */
    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicated_from_id');
    }

    /**
     * @return BelongsTo<ColorPalette, $this>
     */
    public function sourcePalette(): BelongsTo
    {
        return $this->belongsTo(ColorPalette::class, 'source_palette_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /**
     * Sistemas que a URL publica pode resolver: publicos, ativos e com slug.
     *
     * @param  Builder<static>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('visibility', PaletteVisibility::Public->value)
            ->whereNull('archived_at')
            ->whereNotNull('slug');
    }

    public function isPublic(): bool
    {
        return $this->visibility === PaletteVisibility::Public;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
