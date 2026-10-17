<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\Format;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina publica de planos (`/{locale}/pricing`).
 *
 * Exibe o catalogo ativo da tabela `plans` (nada de preco nem de limite fixado
 * no codigo) e as vantagens do plano Pro. O botao dos planos pagos leva ao
 * formulario de intencao de assinatura (`/subscribe?plan=<slug>`), que exige
 * conta cadastrada (ADR-033).
 */
class PricingController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Pricing', [
            'plans' => $this->plans(),
        ]);
    }

    /**
     * Catalogo ativo, na ordem de exibicao.
     *
     * Numeros e dinheiro saem formatados no idioma da requisicao (ADR-026);
     * `isFree` e `interval` saem crus para a tela decidir o layout e o CTA.
     *
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        $plans = Plan::query()
            ->where('active', true)
            ->orderBy('sort')
            ->get()
            ->map(fn (Plan $plan): array => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'price' => Format::money($plan->price_cents, $plan->currency),
                'isFree' => $plan->isFree(),
                'interval' => $plan->interval,
                'monthlyTokens' => Format::number($plan->monthly_tokens),
                'lifetimeFreeUses' => Format::number($plan->lifetime_free_uses),
                'promptChars' => Format::number($plan->max_prompt_chars),
                'schemaSize' => Format::bytes($plan->max_schema_bytes),
            ])
            ->all();

        return array_values($plans);
    }
}
