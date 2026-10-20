<?php

namespace App\Models;

use App\Enums\SqlDialect;
use Database\Factories\OutputTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Alvo de saida do AI SQL Builder (SQL, Eloquent, Prisma, ...).
 *
 * O catalogo e dado: a migracao semeia os nove do SPEC e o admin pode desligar
 * um alvo sem deploy. `prompt_template` aponta para o arquivo versionado em
 * `resources/prompts`; a coluna guarda o caminho, nao o texto (ADR-016).
 *
 * @property int $id
 * @property string $slug
 * @property string $label
 * @property string $language
 * @property list<string> $supported_dialects
 * @property string $prompt_template
 * @property bool $enabled
 * @property int|null $plan_required
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan|null $requiredPlan
 */
#[Fillable([
    'slug',
    'label',
    'language',
    'supported_dialects',
    'prompt_template',
    'enabled',
    'plan_required',
    'sort',
])]
class OutputTarget extends Model
{
    /** @use HasFactory<OutputTargetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supported_dialects' => 'array',
            'enabled' => 'boolean',
            'plan_required' => 'integer',
            'sort' => 'integer',
        ];
    }

    /**
     * O alvo aceita este dialeto? O contrato de saida e validado contra o
     * dialeto pedido, e o alvo pode nao cobrir os quatro.
     */
    public function supportsDialect(SqlDialect $dialect): bool
    {
        return in_array($dialect->value, $this->supported_dialects, true);
    }

    /**
     * @param  Builder<OutputTarget>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function requiredPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_required');
    }

    /**
     * @return HasMany<AiRequest, $this>
     */
    public function aiRequests(): HasMany
    {
        return $this->hasMany(AiRequest::class);
    }
}
