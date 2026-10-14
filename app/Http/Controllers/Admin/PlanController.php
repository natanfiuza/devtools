<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminActionType;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalogo de planos (ADR-027).
 *
 * O painel edita os numeros do plano - preco, franquia, limites de schema e de
 * prompt -, e editar aqui e o jeito previsto de mudar preco: nao existe
 * constante de preco no codigo (regra do CLAUDE.md).
 *
 * O `slug` nao se edita: e a identidade do plano, e o codigo procura o Free
 * por ele (Plan::FREE_SLUG). Desativar resolve o caso de parar de vender um
 * plano sem quebrar quem ja tem.
 */
class PlanController extends Controller
{
    use RecordsAdminActions;

    public function index(): Response
    {
        return Inertia::render('admin/Plans', [
            'plans' => Plan::query()->orderBy('sort')->get()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'slug' => $plan->slug,
                    'name' => $plan->name,
                    'priceCents' => $plan->price_cents,
                    'price' => Format::money($plan->price_cents, $plan->currency),
                    'currency' => $plan->currency,
                    'interval' => $plan->interval,
                    'monthlyTokens' => $plan->monthly_tokens,
                    'maxSchemaBytes' => $plan->max_schema_bytes,
                    'maxPromptChars' => $plan->max_prompt_chars,
                    'lifetimeFreeUses' => $plan->lifetime_free_uses,
                    'active' => $plan->active,
                    'sort' => $plan->sort,
                    'isFree' => $plan->isFree(),
                    'subscribers' => $plan->subscriptions()->current()->count(),
                ])
                ->all(),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'interval' => ['nullable', 'string', 'in:month,year'],
            'monthly_tokens' => ['required', 'integer', 'min:0'],
            'max_schema_bytes' => ['required', 'integer', 'min:0'],
            'max_prompt_chars' => ['required', 'integer', 'min:0'],
            'lifetime_free_uses' => ['required', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
            'sort' => ['required', 'integer', 'min:0'],
        ]);

        $plan->fill($validated);

        // Auditoria com os nomes dos campos que mudaram - e nao com os valores
        // de antes e depois, que so poluiriam a linha sem dizer o que houve.
        $changed = array_keys($plan->getDirty());

        if ($changed === []) {
            return $this->back('info', __('admin.toasts.plan_unchanged'));
        }

        $plan->save();

        $this->record($request, AdminActionType::PlanUpdated, $plan, ['campos' => $changed]);

        return $this->back('success', __('admin.toasts.plan_saved'));
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('admin.plans.index');
    }
}
