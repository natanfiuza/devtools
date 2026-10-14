<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\Format;
use App\Support\Legal;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home publica: hero, grid de ferramentas, destaque do AI SQL Builder e precos.
 *
 * O hero, os chips e o grid sao montados no cliente a partir do registro de
 * tools (`resources/js/tools`), que tambem alimenta a busca e o sitemap. Do
 * servidor vem o que so ele sabe: o catalogo da tabela `plans` - nada de preco
 * nem de limite no codigo - e o contato da venda manual (ADR-027).
 *
 * Um controller, e nao `Route::inertia`, porque as props dependem do idioma da
 * requisicao: o preco e os numeros saem formatados no idioma da tela (ADR-026).
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'plans' => $this->plans(),
            'contactEmail' => Legal::contactEmail(),
        ]);
    }

    /**
     * Catalogo ativo, na ordem de exibicao. A home mostra o que o admin deixou
     * ativo: desativar um plano o tira do site sem apagar a linha nem tocar em
     * quem ja assina.
     *
     * Numeros e dinheiro saem formatados daqui (o texto da linha, inclusive a
     * unidade, vem de `lang/{locale}/home.php`); o que a tela precisa comparar
     * - `isFree`, `interval` - vem cru.
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
