<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Um plano do catalogo. Precos e limites sao dado, nunca constante de codigo.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $price_cents
 * @property string $currency
 * @property string|null $interval
 * @property int $monthly_tokens
 * @property int $max_schema_bytes
 * @property int $max_prompt_chars
 * @property int $lifetime_free_uses
 * @property bool $active
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'slug',
    'name',
    'price_cents',
    'currency',
    'interval',
    'monthly_tokens',
    'max_schema_bytes',
    'max_prompt_chars',
    'lifetime_free_uses',
    'active',
    'sort',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * O plano de quem nao tem assinatura. A linha e criada pela migracao do
     * catalogo e nunca excluida - no maximo desativada.
     */
    public const FREE_SLUG = 'free';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'monthly_tokens' => 'integer',
            'max_schema_bytes' => 'integer',
            'max_prompt_chars' => 'integer',
            'lifetime_free_uses' => 'integer',
            'active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * O plano gratuito: o piso de qualquer conta, com ou sem assinatura.
     */
    public static function free(): self
    {
        return self::query()->where('slug', self::FREE_SLUG)->firstOrFail();
    }

    public function isFree(): bool
    {
        return $this->slug === self::FREE_SLUG;
    }

    /**
     * O Free conta usos vitalicios; os planos pagos contam tokens no periodo.
     * E o que escolhe a unidade da cota (App\Support\Quota).
     */
    public function countsByUses(): bool
    {
        return $this->isFree();
    }

    /**
     * Fim do periodo que comeca em `$start`, ou nulo para plano sem periodo
     * (o Free, que nao expira).
     *
     * Sem overflow de proposito: assinar em 31/01 da um periodo que termina em
     * 28/02 (ou 29/02 em ano bissexto), nao em 03/03. Um mes "pulado" pelo
     * calendario encurtaria a franquia de quem pagou o mes inteiro.
     */
    public function periodEndFrom(CarbonInterface $start): ?CarbonInterface
    {
        return match ($this->interval) {
            'month' => $start->addMonthNoOverflow(),
            'year' => $start->addYearNoOverflow(),
            default => null,
        };
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
