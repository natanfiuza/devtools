<?php

namespace App\Http\Controllers\Account;

use App\Actions\Payments\CancelRecurring;
use App\Enums\CheckoutStatus;
use App\Enums\SubscriptionIntentStatus;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionIntent;
use App\Models\User;
use App\Support\Format;
use App\Support\Quota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Faturas: a assinatura vigente, o que ja foi pago e o estado da renovacao.
 *
 * O modelo de cobranca continua sendo a venda manual (ADR-027), mas com o
 * gateway (F6) a conta passa a poder encerrar a propria renovacao automatica:
 * o periodo pago segue valendo ate o fim e o que para e a cobranca seguinte.
 * O botao so aparece quando ha uma preapproval viva; a tela tambem mostra o
 * proximo periodo empilhado, a falha de renovacao e o checkout em andamento.
 */
class BillingController extends Controller
{
    private const PER_PAGE = 20;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $quota = Quota::for($user);
        $subscription = $quota->subscription;

        /** @var SubscriptionIntent|null $pendingIntent */
        $pendingIntent = $user->subscriptionIntents()
            ->with('plan')
            ->where('status', SubscriptionIntentStatus::Pending->value)
            ->latest('id')
            ->first();

        $payments = $user->payments()
            ->with('plan')
            ->orderByDesc('paid_at')
            ->paginate(self::PER_PAGE);

        return Inertia::render('dashboard/Billing', [
            'plan' => [
                'name' => $quota->plan->name,
                'interval' => $quota->plan->interval,
                'isFree' => $quota->plan->isFree(),
                'price' => Format::money($quota->plan->price_cents, $quota->plan->currency),
            ],
            'subscription' => $this->subscription($subscription),
            'nextSubscription' => $this->nextSubscription($user),
            'lastCheckout' => $this->lastCheckout($user),
            'pendingIntent' => $pendingIntent === null ? null : [
                'id' => $pendingIntent->id,
                'planName' => $pendingIntent->plan->name,
                'planSlug' => $pendingIntent->plan->slug,
                'status' => $pendingIntent->status->value,
                'paymentMethod' => $pendingIntent->payment_method->value,
                'createdAt' => Format::date($pendingIntent->created_at),
            ],
            'payments' => $payments->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'plan' => $payment->plan?->name,
                'amount' => Format::money($payment->amount_cents, $payment->currency),
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'paidAt' => Format::date($payment->paid_at),
                'note' => $payment->note,
            ]),
            'total' => $payments->total(),
        ]);
    }

    /**
     * Encerra a renovacao automatica sem encerrar o periodo pago (F6).
     *
     * O gateway e chamado primeiro pela acao; se ele recusar, nada muda no
     * banco e a tela mostra o erro. O acesso segue ate `current_period_end`.
     */
    public function cancelRecurring(Request $request, CancelRecurring $cancelRecurring): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $canceled = $cancelRecurring($user);
        } catch (PaymentGatewayException $exception) {
            // A excecao nao carrega corpo de resposta nem token, entao pode ir
            // para o log; a tela recebe so a mensagem segura.
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('account.billing.renewal.cancel_failed'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => $canceled > 0 ? 'success' : 'info',
            'message' => $canceled > 0
                ? __('account.billing.renewal.canceled_toast')
                : __('account.billing.renewal.nothing_to_cancel'),
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subscription(?Subscription $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }

        return [
            'plan' => $subscription->plan->name,
            'interval' => $subscription->plan->interval,
            'gateway' => $subscription->gateway,
            'status' => $subscription->status->value,
            'start' => Format::date($subscription->current_period_start),
            'end' => Format::date($subscription->current_period_end),
            'current' => $subscription->isCurrent(),
            'renewalCanceledAt' => Format::date($subscription->renewal_canceled_at),
            'renewalFailedAt' => Format::date($subscription->renewal_failed_at),
            'renewable' => $this->isRenewable($subscription),
        ];
    }

    /**
     * Ha uma preapproval viva para encerrar? A renovacao ja cancelada nao
     * oferece o botao de novo; a linha manual nunca teve preapproval.
     */
    private function isRenewable(Subscription $subscription): bool
    {
        return $subscription->gateway === 'mercadopago'
            && is_string($subscription->gateway_ref)
            && $subscription->gateway_ref !== ''
            && $subscription->renewal_canceled_at === null
            && $subscription->isCurrent();
    }

    /**
     * Proximo periodo ja pago e ainda nao comecado (renovacao antecipada,
     * troca de plano ou empilhamento de checkout).
     *
     * @return array<string, mixed>|null
     */
    private function nextSubscription(User $user): ?array
    {
        $subscription = $user->nextSubscription();

        if ($subscription === null) {
            return null;
        }

        return [
            'plan' => $subscription->plan->name,
            'start' => Format::date($subscription->current_period_start),
            'end' => Format::date($subscription->current_period_end),
        ];
    }

    /**
     * Checkout ainda pendente da conta, do mais recente para o mais antigo.
     *
     * A sessao autorizada de uma recorrencia nao expira: o primeiro pagamento
     * pode levar cerca de uma hora, e a tela mostra "processando" ate o webhook
     * de aprovacao chegar. Um checkout avulso vencido nao entra - nao ha o que
     * acompanhar.
     *
     * @return array<string, mixed>|null
     */
    private function lastCheckout(User $user): ?array
    {
        $session = $user->checkoutSessions()
            ->with('plan')
            ->where('status', CheckoutStatus::Pending->value)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if (! $session instanceof CheckoutSession) {
            return null;
        }

        return [
            'ulid' => $session->ulid,
            'mode' => $session->mode->value,
            'status' => $session->effectiveStatus()->value,
            'planName' => $session->plan->name,
            'planSlug' => $session->plan->slug,
            'processing' => $session->authorized_at !== null,
            'createdAt' => Format::date($session->created_at),
        ];
    }
}
