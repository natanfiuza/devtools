<?php

namespace App\Models;

use App\Enums\CustomMetricFormatter;
use Database\Factories\BadgeCustomProviderDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Metrica de um provider customizado (F7, ADR-087).
 *
 * `pointer` e um JSON Pointer RFC 6901 e `formatter` decide como o escalar
 * vira texto. O slug e imutavel depois da criacao para nao quebrar URLs ja
 * publicadas; label/pointer/formatter podem ser editados.
 *
 * @property string $id
 * @property string $provider_id
 * @property string $slug
 * @property string $label
 * @property string $pointer
 * @property CustomMetricFormatter $formatter
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BadgeCustomProvider $provider
 */
#[Fillable(['id', 'provider_id', 'slug', 'label', 'pointer', 'formatter', 'sort'])]
class BadgeCustomProviderDefinition extends Model
{
    /** @use HasFactory<BadgeCustomProviderDefinitionFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'formatter' => CustomMetricFormatter::class,
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BadgeCustomProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(BadgeCustomProvider::class, 'provider_id');
    }
}
