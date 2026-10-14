<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\Format;
use App\Support\Quota;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Faturas: a assinatura vigente e o que ja foi pago.
 *
 * A conta ve o que o admin registrou - o modelo de cobranca do bloco e a venda
 * manual (ADR-027), entao aqui nao ha botao de assinar nem de cancelar: isso
 * chega com o gateway, na F6. O que existe e a leitura do proprio historico.
 */
class BillingController extends Controller
{
    private const PER_PAGE = 20;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $quota = Quota::for($user);
        $subscription = $quota->subscription;

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
        ];
    }
}
