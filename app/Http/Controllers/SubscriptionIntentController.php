<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CancelRecurring;
use App\Contracts\Payments\PaymentGateway;
use App\Enums\SubscriptionIntentPaymentMethod;
use App\Enums\SubscriptionIntentStatus;
use App\Http\Requests\StoreSubscriptionIntentRequest;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\SubscriptionIntent;
use App\Models\User;
use App\Notifications\SubscriptionIntentSubmitted;
use App\Support\Format;
use App\Support\Legal;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulario de intencao de assinatura Pro (ADR-033).
 *
 * Visitante sem conta que clica em assinar na pagina de Planos chega aqui pelo
 * GET `/subscribe?plan=<slug>`: guardamos a URL na sessao (`url.intended`) e o
 * mandamos para o cadastro (`/{locale}/register`). Ao terminar de criar a conta
 * (ou entrar), o `redirect()->intended(...)` do Fortify/Socialite traz a pessoa
 * de volta para este formulario com o plano pre-selecionado.
 */
class SubscriptionIntentController extends Controller
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CancelRecurring $cancelRecurring,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            redirect()->setIntendedUrl($request->fullUrl());

            return redirect()->route('register', [
                'locale' => Locales::slug(app()->getLocale()),
            ]);
        }

        $plans = $this->paidPlans();
        $requestedPlan = $request->query('plan');
        $slugs = array_column($plans, 'slug');

        $selectedPlan = is_string($requestedPlan) && in_array($requestedPlan, $slugs, true)
            ? $requestedPlan
            : ($slugs[0] ?? 'pro-monthly');

        $pending = $user->subscriptionIntents()
            ->with('plan')
            ->where('status', SubscriptionIntentStatus::Pending->value)
            ->latest('id')
            ->first();

        return Inertia::render('Subscribe', [
            'plans' => $plans,
            'selectedPlan' => $pending !== null && ! $request->has('plan')
                ? $pending->plan->slug
                : $selectedPlan,
            'username' => $user->username ?? ($pending !== null ? $pending->username : ''),
            'pendingIntent' => $pending === null ? null : [
                'id' => $pending->id,
                'planSlug' => $pending->plan->slug,
                'username' => $pending->username,
                'phone' => $pending->phone,
                'document' => $pending->document,
                'paymentMethod' => $pending->payment_method->value,
                'notes' => $pending->notes ?? '',
                'createdAt' => Format::date($pending->created_at),
            ],
            'status' => $request->session()->get('status'),
            'onlineCheckoutAvailable' => $this->gateway->isAvailable(),
            'recurringPlanSlugs' => $this->recurringPlanSlugs($user),
            'checkout' => $this->checkoutState($request, $user),
        ]);
    }

    public function store(StoreSubscriptionIntentRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $plan = Plan::query()
            ->where('slug', $validated['plan'])
            ->where('active', true)
            ->sole();

        /** @var SubscriptionIntent $intent */
        $intent = DB::transaction(function () use ($user, $plan, $validated): SubscriptionIntent {
            $user->username = $validated['username'];
            $user->save();

            $existing = $user->subscriptionIntents()
                ->where('status', SubscriptionIntentStatus::Pending->value)
                ->latest('id')
                ->first();

            $payload = [
                'plan_id' => $plan->id,
                'username' => $validated['username'],
                'phone' => $validated['phone'],
                'document' => $validated['document'] ?? null,
                'payment_method' => SubscriptionIntentPaymentMethod::from($validated['payment_method']),
                'notes' => $validated['notes'] ?? null,
                'status' => SubscriptionIntentStatus::Pending,
            ];

            if ($existing !== null) {
                $existing->fill($payload)->save();

                return $existing;
            }

            return $user->subscriptionIntents()->create($payload);
        });

        $contactEmail = Legal::contactEmail();

        if ($contactEmail !== null) {
            $admin = User::query()
                ->where('email', $contactEmail)
                ->first();

            $targetLocale = $admin?->preferredLocale() ?? $user->preferredLocale();

            Notification::route('mail', $contactEmail)
                ->notify((new SubscriptionIntentSubmitted($intent))->locale($targetLocale));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('home.subscribe.submitted_toast'),
        ]);

        return redirect()
            ->route('subscribe', ['plan' => $plan->slug])
            ->with('status', 'intent-submitted');
    }

    /**
     * Planos pagos ativos para escolha no formulario.
     *
     * @return list<array<string, mixed>>
     */
    private function paidPlans(): array
    {
        $plans = Plan::query()
            ->where('active', true)
            ->where('price_cents', '>', 0)
            ->orderBy('sort')
            ->get()
            ->map(fn (Plan $plan): array => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'price' => Format::money($plan->price_cents, $plan->currency),
                'isFree' => false,
                'interval' => $plan->interval,
                'monthlyTokens' => Format::number($plan->monthly_tokens),
                'lifetimeFreeUses' => Format::number($plan->lifetime_free_uses),
                'promptChars' => Format::number($plan->max_prompt_chars),
                'schemaSize' => Format::bytes($plan->max_schema_bytes),
            ])
            ->all();

        return array_values($plans);
    }

    /**
     * Planos com renovacao automatica viva. A tela bloqueia uma segunda
     * recorrencia no mesmo plano e avisa antes de trocar de plano.
     *
     * @return list<string>
     */
    private function recurringPlanSlugs(User $user): array
    {
        $slugs = $this->cancelRecurring
            ->recurringSubscriptions($user)
            ->loadMissing('plan')
            ->pluck('plan.slug')
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->unique()
            ->all();

        return array_values($slugs);
    }

    /**
     * Sessao de checkout devolvida na URL (`?checkout=<ulid>`), somente se for
     * da propria conta. O status e o efetivo: pendente vencido aparece expirado
     * sem alterar o banco - a varredura de `payments:expire` e quem persiste.
     *
     * @return array{ulid: string, status: string, mode: string, planSlug: string, createdAt: string|null}|null
     */
    private function checkoutState(Request $request, User $user): ?array
    {
        $ulid = $request->query('checkout');

        if (! is_string($ulid) || $ulid === '') {
            return null;
        }

        $session = $user->checkoutSessions()
            ->where('ulid', $ulid)
            ->with('plan')
            ->first();

        if (! $session instanceof CheckoutSession) {
            return null;
        }

        return [
            'ulid' => $session->ulid,
            'status' => $session->effectiveStatus()->value,
            'mode' => $session->mode->value,
            'planSlug' => $session->plan->slug,
            'createdAt' => $session->created_at !== null
                ? Format::date($session->created_at)
                : null,
        ];
    }
}
