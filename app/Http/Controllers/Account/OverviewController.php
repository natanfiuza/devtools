<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Support\Format;
use App\Support\Quota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visao geral da conta: plano, cota e vencimento num lugar so.
 *
 * E a primeira tela de quem entra, entao ela responde tres perguntas - em que
 * plano eu estou, quanto ainda cabe e quando vence - sem mandar ninguem
 * procurar. O que a tela mostra e leitura do que o modelo ja resolve
 * (`effectivePlan`, `Quota`); nenhuma regra de cota mora aqui.
 */
class OverviewController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $quota = Quota::for($user);
        $subscription = $quota->subscription;

        return Inertia::render('Dashboard', [
            'plan' => [
                'name' => $quota->plan->name,
                'interval' => $quota->plan->interval,
                'isFree' => $quota->plan->isFree(),
                'price' => Format::money($quota->plan->price_cents, $quota->plan->currency),
                'currency' => $quota->plan->currency,
            ],
            'quota' => [
                'unit' => $quota->unit(),
                'limit' => $quota->limit(),
                // Numero cru: consumo acima da franquia aparece como esta, e
                // nao arredondado a favor de quem le.
                'used' => $quota->used(),
                'remaining' => $quota->remaining(),
                'exhausted' => $quota->exhausted(),
                'windowStart' => Format::date($quota->windowStart()),
                'windowEnd' => Format::date($quota->windowEnd()),
            ],
            'subscription' => $this->subscription($subscription),
            // Contagem, e nao historico: a lista de consumo por uso chega na
            // F5, quando existir consumo de ferramenta para listar.
            'payments' => $user->payments()->count(),
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
