<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionIntentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionIntent;
use App\Models\User;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hub unificado de gestao de assinaturas no painel administrativo.
 *
 * Agrega as quatro visoes complementares do ciclo de receita e retencao:
 * 1. Assinaturas vigentes (ativas)
 * 2. Solicitacoes de assinatura (intencoes Pro)
 * 3. Pagamentos e faturas (com registro manual e estornos)
 * 4. Contas excluidas (em carencia de 30 dias via soft delete e contas anonimizadas)
 */
class SubscriptionController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->trim()->value();
        if (! in_array($tab, ['active', 'intents', 'payments', 'deleted'], true)) {
            $tab = 'active';
        }

        $search = $request->string('q')->trim()->value();
        $status = $request->string('status')->trim()->value();
        $planId = $request->integer('plan');

        $activePlans = Plan::query()->active()->orderBy('sort')->get();

        return Inertia::render('admin/Subscriptions', [
            'tab' => $tab,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'plan' => $planId > 0 ? $planId : null,
            ],
            'stats' => [
                'active' => Subscription::query()->current()->count(),
                'pendingIntents' => SubscriptionIntent::query()
                    ->where('status', SubscriptionIntentStatus::Pending->value)
                    ->count(),
                'revenueMonth' => $this->revenueThisMonth(),
                'gracePeriod' => User::query()->inDeletionGracePeriod()->count(),
            ],
            'plans' => $activePlans->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'interval' => $plan->interval,
                'price' => Format::money($plan->price_cents, $plan->currency),
            ])->all(),
            'subscriptions' => $tab === 'active' ? $this->activeSubscriptions($search, $planId) : null,
            'intents' => $tab === 'intents' ? $this->subscriptionIntents($search, $status) : null,
            'payments' => $tab === 'payments' ? $this->payments($search, $status) : null,
            'paymentOptions' => $tab === 'payments' ? [
                'plans' => $activePlans->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => Format::money($plan->price_cents, $plan->currency),
                ])->all(),
                'methods' => array_map(
                    fn (PaymentMethod $method): string => $method->value,
                    PaymentMethod::cases(),
                ),
            ] : null,
            'deletedAccounts' => $tab === 'deleted' ? $this->deletedAccounts($search, $status) : null,
        ]);
    }

    /**
     * @return array{cents: int, formatted: string}
     */
    private function revenueThisMonth(): array
    {
        $cents = (int) Payment::query()
            ->where('status', PaymentStatus::Paid->value)
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount_cents');

        return [
            'cents' => $cents,
            'formatted' => Format::money($cents, (string) config('billing.currency')),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    private function activeSubscriptions(string $search, int $planId): LengthAwarePaginator
    {
        return Subscription::query()
            ->current()
            ->with(['user' => fn ($query) => $query->withTrashed(), 'plan'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($planId > 0, fn (Builder $query) => $query->where('plan_id', $planId))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Subscription $sub): array => [
                'id' => $sub->id,
                'userId' => $sub->user_id,
                'userName' => $sub->user->name,
                'userEmail' => $sub->user->email,
                'isUserTrashed' => $sub->user->trashed(),
                'planId' => $sub->plan_id,
                'planName' => $sub->plan->name,
                'interval' => $sub->plan->interval,
                'gateway' => $sub->gateway,
                'status' => $sub->status->value,
                'start' => Format::date($sub->current_period_start),
                'end' => Format::date($sub->current_period_end),
                'createdAt' => Format::date($sub->created_at),
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    private function subscriptionIntents(string $search, string $status): LengthAwarePaginator
    {
        return SubscriptionIntent::query()
            ->with(['user', 'plan'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('username', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('document', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function (Builder $uq) use ($search): void {
                            $uq->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                in_array($status, ['pending', 'fulfilled', 'canceled'], true),
                fn (Builder $query) => $query->where('status', $status),
            )
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (SubscriptionIntent $intent): array => [
                'id' => $intent->id,
                'userId' => $intent->user_id,
                'userName' => $intent->user->name,
                'userEmail' => $intent->user->email,
                'username' => $intent->username,
                'planSlug' => $intent->plan->slug,
                'planName' => $intent->plan->name,
                'planPrice' => Format::money($intent->plan->price_cents, $intent->plan->currency),
                'planAmount' => number_format($intent->plan->price_cents / 100, 2, '.', ''),
                'interval' => $intent->plan->interval,
                'phone' => $intent->phone,
                'document' => $intent->document,
                'paymentMethod' => $intent->payment_method->value,
                'notes' => $intent->notes,
                'status' => $intent->status->value,
                'createdAt' => Format::date($intent->created_at),
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    private function payments(string $search, string $status): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['user' => fn ($query) => $query->withTrashed(), 'plan'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('email', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->when(
                PaymentStatus::tryFrom($status) !== null,
                fn (Builder $query) => $query->where('status', $status),
            )
            ->orderByDesc('paid_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'userId' => $payment->user_id,
                'account' => $payment->user->email,
                'userName' => $payment->user->name,
                'plan' => $payment->plan?->name,
                'amount' => Format::money($payment->amount_cents, $payment->currency),
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'paidAt' => Format::dateTime($payment->paid_at),
                'note' => $payment->note,
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    private function deletedAccounts(string $search, string $status): LengthAwarePaginator
    {
        return User::withTrashed()
            ->where(function (Builder $query): void {
                $query->whereNotNull('deleted_at')
                    ->orWhereNotNull('anonymized_at');
            })
            ->when($status === 'grace', function (Builder $query): void {
                $query->whereNotNull('deleted_at')
                    ->whereNull('anonymized_at');
            })
            ->when($status === 'anonymized', function (Builder $query): void {
                $query->whereNotNull('anonymized_at');
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(function (User $user): array {
                $isAnonymized = $user->isAnonymized();
                $deletedAt = $user->deleted_at ?? $user->anonymized_at;
                $daysRemaining = 0;

                if (! $isAnonymized && $user->deleted_at !== null) {
                    $daysPassed = (int) $user->deleted_at->diffInDays(now());
                    $daysRemaining = max(0, 30 - $daysPassed);
                }

                return [
                    'id' => $user->id,
                    'name' => $isAnonymized ? (string) __('admin.users.anonymized') : $user->name,
                    'email' => $user->email,
                    'isAnonymized' => $isAnonymized,
                    'isTrashed' => $user->trashed(),
                    'deletedAt' => Format::dateTime($deletedAt),
                    'anonymizedAt' => $user->anonymized_at !== null ? Format::dateTime($user->anonymized_at) : null,
                    'daysRemaining' => $daysRemaining,
                    'canRestore' => ! $isAnonymized && $user->trashed(),
                ];
            });
    }
}
