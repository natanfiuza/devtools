<?php

namespace App\Support;

use App\Enums\LedgerEntryType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estado da Cota de uma conta (termo do glossario).
 *
 * Nao existe coluna de saldo: o consumo e a soma do livro-razao, menos o que foi
 * estornado ou concedido. Saldo em coluna e o que se perde quando duas escritas
 * concorrem.
 *
 * A unidade depende do plano: o Free conta usos vitalicios, os pagos contam
 * tokens do periodo corrente da assinatura. Quem consome de verdade e a F5; aqui
 * mora so a leitura do estado.
 */
final class Quota
{
    private function __construct(
        public readonly User $user,
        public readonly Plan $plan,
        public readonly ?Subscription $subscription,
    ) {}

    public static function for(User $user): self
    {
        $subscription = $user->activeSubscription();

        if ($subscription === null) {
            return new self($user, Plan::free(), null);
        }

        return new self($user, $subscription->plan, $subscription);
    }

    /**
     * Unidade da cota: `uses` no Free, `tokens` nos pagos. Serve de chave de
     * traducao para a tela - a unidade e do dado, o texto e do idioma.
     */
    public function unit(): string
    {
        return $this->plan->countsByUses() ? 'uses' : 'tokens';
    }

    /**
     * Franquia do plano na unidade corrente. Zero significa "sem uso liberado".
     */
    public function limit(): int
    {
        return $this->plan->countsByUses()
            ? $this->plan->lifetime_free_uses
            : $this->plan->monthly_tokens;
    }

    /**
     * Consumo do periodo: debitos menos estornos e concessoes.
     */
    public function used(): int
    {
        $column = $this->unit();

        $debits = (int) $this->query()->where('type', LedgerEntryType::Debit->value)->sum($column);

        $credits = (int) $this->query()
            ->whereIn('type', LedgerEntryType::creditValues())
            ->sum($column);

        return $debits - $credits;
    }

    /**
     * Nunca negativo: consumo acima da franquia aparece em `exhausted`, nao
     * como saldo devedor.
     */
    public function remaining(): int
    {
        return max(0, $this->limit() - $this->used());
    }

    public function exhausted(): bool
    {
        return $this->remaining() === 0;
    }

    /**
     * Representacao da cota para a API e para as props da tool. As datas saem
     * em ISO 8601; a traducao do periodo e da tela.
     *
     * @return array{
     *     unit: string,
     *     limit: int,
     *     used: int,
     *     remaining: int,
     *     exhausted: bool,
     *     window: array{start: string|null, end: string|null}
     * }
     */
    public function toArray(): array
    {
        return [
            'unit' => $this->unit(),
            'limit' => $this->limit(),
            'used' => $this->used(),
            'remaining' => $this->remaining(),
            'exhausted' => $this->exhausted(),
            'window' => [
                'start' => $this->windowStart()?->toIso8601String(),
                'end' => $this->windowEnd()?->toIso8601String(),
            ],
        ];
    }

    /**
     * Inicio da janela contada. Nulo no Free: o limite dele e vitalicio.
     *
     * A interface (e nao uma classe) porque a data do cast e a do `Date::use`
     * do app - prometer CarbonImmutable aqui prenderia a assinatura a uma
     * escolha que e do provider.
     */
    public function windowStart(): ?CarbonInterface
    {
        return $this->subscription?->current_period_start;
    }

    /**
     * Fim da janela contada. Nulo quando nao ha fim - no Free e na concessao
     * sem prazo.
     */
    public function windowEnd(): ?CarbonInterface
    {
        return $this->subscription?->current_period_end;
    }

    /**
     * Lancamentos que entram na conta: os da conta e, quando ha periodo, so os
     * de dentro dele.
     *
     * @return HasMany<UsageLedger, User>
     */
    private function query(): HasMany
    {
        $query = $this->user->usageLedger();
        $start = $this->windowStart();

        if ($start !== null) {
            $query->where('created_at', '>=', $start);
        }

        return $query;
    }
}
