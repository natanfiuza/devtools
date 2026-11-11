<?php

namespace App\Models;

use App\Enums\PaletteKind;
use App\Enums\PaletteVisibility;
use Database\Factories\ColorPaletteFactory;
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
 * Paleta persistida de um usuario (F4, ADR-123).
 *
 * `colors` e `roles` sao o retrato efetivo do momento em que a paleta foi
 * salva: reabrir nao recalcula a escala nem os papeis. `slug` so nasce na
 * primeira publicacao, nunca muda ao renomear e e liberado no arquivamento
 * (`archived_slug` guarda o valor para a restauracao). `duplications_count`
 * e a metrica "uso": quantas vezes outros usuarios duplicaram a paleta.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $duplicated_from_id
 * @property string $name
 * @property string|null $description
 * @property PaletteVisibility $visibility
 * @property PaletteKind $kind
 * @property int $algorithm_version
 * @property array{base: string, items: list<array{hex: string, tone?: int}>} $colors
 * @property array{light: array<string, string>, dark: array<string, string>} $roles
 * @property array<string, string>|null $source
 * @property int $colors_count
 * @property int $duplications_count
 * @property string|null $slug
 * @property string|null $archived_slug
 * @property Carbon|null $archived_at
 * @property int|null $og_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read ColorPalette|null $duplicatedFrom
 */
#[Fillable([
    'id',
    'user_id',
    'duplicated_from_id',
    'name',
    'description',
    'visibility',
    'kind',
    'algorithm_version',
    'colors',
    'roles',
    'source',
    'colors_count',
    'duplications_count',
    'slug',
    'archived_slug',
    'archived_at',
    'og_version',
])]
class ColorPalette extends Model
{
    /** @use HasFactory<ColorPaletteFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => PaletteVisibility::class,
            'kind' => PaletteKind::class,
            'algorithm_version' => 'integer',
            'colors' => 'array',
            'roles' => 'array',
            'source' => 'array',
            'colors_count' => 'integer',
            'duplications_count' => 'integer',
            'archived_at' => 'datetime',
            'og_version' => 'integer',
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
        return $this->belongsToMany(ColorTag::class, 'color_palette_color_tag');
    }

    /**
     * @return BelongsTo<ColorPalette, $this>
     */
    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_id');
    }

    /**
     * @return HasMany<ColorPalette, $this>
     */
    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicated_from_id');
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
     * Paletas que a URL publica pode resolver: publicas, ativas e com slug.
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

    public function hasOg(): bool
    {
        return $this->og_version !== null;
    }
}
