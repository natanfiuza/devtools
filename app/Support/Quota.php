<?php

namespace App\Support;

use App\Enums\LedgerEntryType;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Estado da Cota de uma conta (termo do glossario).
 *
 * Nao existe coluna de saldo: o direito e o consumo sao calculados na leitura.
 * Saldo em coluna e o que se perde quando duas escritas concorrem.
 *
 * A unidade depende do plano vigente:
 * - Free: conta usos vitalicios (`lifetime_free_uses` menos debitos de usos,
 *   mais estornos e concessoes de usos).
 * - Planos pagos (ADR-054): conta tokens **acumulativos**. Cada mes iniciado de
 *   cada assinatura paga nao estornada credita `plan.monthly_tokens` (1 mes no
 *   mensal, ate 12 no anual), somado as concessoes manuais (`grant`); o consumo
 *   soma todos os debitos de tokens descontados os estornos de falha (`refund`).
 *   O que sobra num mes passa para o seguinte, e se a assinatura vencer o saldo
 *   fica guardado ate a conta voltar a ter um plano pago ativo.
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
     * Total creditado na unidade corrente: usos vitalicios no Free; nos planos
     * pagos, a soma dos lotes mensais ja iniciados de todas as assinaturas nao
     * estornadas mais as concessoes manuais de tokens (`grant`).
     */
    public function limit(): int
    {
        if ($this->plan->countsByUses()) {
            return $this->plan->lifetime_free_uses;
        }

        $grants = (int) $this->user->usageLedger()
            ->where('type', LedgerEntryType::Grant->value)
            ->sum('tokens');

        return $this->subscriptionCredits() + $grants;
    }

    /**
     * Consumo acumulado na unidade corrente.
     *
     * No Free, debitos menos estornos e concessoes (mantem a semantica original
     * onde concessao reduz o usado). Nos planos pagos, debitos de tokens de
     * todo o historico menos os estornos de falha (`refund`) - as concessoes
     * entram em `limit()` como credito acumulado.
     */
    public function used(): int
    {
        $column = $this->unit();

        if ($this->plan->countsByUses()) {
            $debits = (int) $this->user->usageLedger()
                ->where('type', LedgerEntryType::Debit->value)
                ->sum($column);

            $credits = (int) $this->user->usageLedger()
                ->whereIn('type', LedgerEntryType::creditValues())
                ->sum($column);

            return $debits - $credits;
        }

        $debits = (int) $this->user->usageLedger()
            ->where('type', LedgerEntryType::Debit->value)
            ->sum('tokens');

        $refunds = (int) $this->user->usageLedger()
            ->where('type', LedgerEntryType::Refund->value)
            ->sum('tokens');

        return max(0, $debits - $refunds);
    }

    /**
     * Nunca negativo: consumo acima do direito aparece em `exhausted`, nao
     * como saldo devedor. Se um periodo ja consumido for estornado, o excesso
     * fica abatido do proximo credito que entrar (ADR-054).
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
     * Quando entra o proximo lote de `monthly_tokens`: o proximo mes dentro de
     * um periodo anual vigente, ou o inicio do proximo periodo ja pago. Nulo no
     * Free, em concessao sem fim ou quando nao ha proximo lote contratado.
     */
    public function nextCreditAt(): ?CarbonInterface
    {
        if ($this->plan->countsByUses() || $this->subscription === null) {
            return null;
        }

        $current = $this->subscription;
        $now = Date::now();

        if (
            $current->current_period_start !== null
            && $current->current_period_end !== null
            && $current->plan->interval === 'year'
        ) {
            $started = $this->startedMonthsFor($current, $now);

            if ($started < 12) {
                $nextMonth = Date::parse($current->current_period_start)->addMonthsNoOverflow($started);

                if ($nextMonth->lessThan($current->current_period_end)) {
                    return $nextMonth;
                }
            }
        }

        $next = $this->user->nextSubscription();

        return $next?->current_period_start;
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
     *     window: array{start: string|null, end: string|null},
     *     next_credit_at: string|null
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
            'next_credit_at' => $this->nextCreditAt()?->toIso8601String(),
        ];
    }

    /**
     * Inicio do periodo vigente. Nulo no Free.
     */
    public function windowStart(): ?CarbonInterface
    {
        return $this->subscription?->current_period_start;
    }

    /**
     * Fim do periodo vigente. Nulo no Free e na concessao sem prazo.
     */
    public function windowEnd(): ?CarbonInterface
    {
        return $this->subscription?->current_period_end;
    }

    /**
     * Soma dos tokens creditados pelas assinaturas pagas da conta (ADR-054).
     *
     * Entram na soma as linhas nao estornadas (`refunded_at is null`) que
     * estejam ativas ou tenham `ended_at` gravado (troca de plano / downgrade).
     * Linhas `canceled` antigas sem `ended_at` valem 0 porque nao ha como
     * distinguir estorno de troca antes da migracao.
     */
    private function subscriptionCredits(): int
    {
        $subscriptions = $this->user->subscriptions()
            ->with('plan')
            ->whereNull('refunded_at')
            ->where(function ($query): void {
                $query->where('status', SubscriptionStatus::Active->value)
                    ->orWhereNotNull('ended_at');
            })
            ->get();

        $now = Date::now();
        $total = 0;

        foreach ($subscriptions as $subscription) {
            $plan = $subscription->plan;

            if ($plan->isFree() || $plan->monthly_tokens <= 0) {
                continue;
            }

            $months = $this->startedMonthsFor($subscription, $now);
            $total += $months * $plan->monthly_tokens;
        }

        return $total;
    }

    /**
     * Quantos meses de franquia ja comecaram dentro de uma linha de assinatura.
     *
     * - Mensal ou vitalicia: 1 a partir do inicio do periodo (0 se ainda e
     *   futura ou se foi encerrada antes de comecar).
     * - Anual: 1 no inicio + 1 a cada mes de calendario (`addMonthsNoOverflow`)
     *   alcancado ate `min(agora, current_period_end, ended_at)`, limitado a 12.
     */
    private function startedMonthsFor(Subscription $subscription, CarbonInterface $now): int
    {
        $start = $subscription->current_period_start;

        if ($start === null) {
            return $subscription->ended_at !== null && $subscription->ended_at->lessThanOrEqualTo($now)
                ? 1
                : 1;
        }

        $cutoff = $now;

        if ($subscription->current_period_end !== null && $subscription->current_period_end->lessThan($cutoff)) {
            $cutoff = $subscription->current_period_end;
        }

        if ($subscription->ended_at !== null && $subscription->ended_at->lessThan($cutoff)) {
            $cutoff = $subscription->ended_at;
        }

        if ($start->greaterThan($cutoff)) {
            return 0;
        }

        if ($subscription->plan->interval !== 'year') {
            return 1;
        }

        $origin = Date::parse($start);
        $months = 0;

        for ($i = 0; $i < 12; $i++) {
            $milestone = $origin->addMonthsNoOverflow($i);

            // Um mes comeca quando o relogio alcanca o seu marco; mas se o
            // periodo ja encerrou exatamente naquele instante (`ended_at` ou
            // `current_period_end`), o mes novo nao chegou a abrir (exceto o
            // primeiro mes no proprio inicio de uma assinatura vigente).
            if ($i === 0) {
                $months = 1;

                continue;
            }

            if ($milestone->lessThanOrEqualTo($cutoff)) {
                if (
                    ($subscription->ended_at !== null && $milestone->equalTo($subscription->ended_at))
                    || ($subscription->current_period_end !== null && $milestone->equalTo($subscription->current_period_end))
                ) {
                    break;
                }

                $months++;
            } else {
                break;
            }
        }

        return $months;
    }
}
