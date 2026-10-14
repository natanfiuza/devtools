<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Format;
use App\Support\Quota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contas: a lista com busca e filtros, e o detalhe de uma conta.
 *
 * O detalhe e a unica tela que reune tudo o que se sabe sobre uma conta -
 * identidade, situacao, assinatura, faturas, consumo, sessoes e vinculos - e e
 * dela que saem as nove acoes (UserActionController).
 */
class UserController extends Controller
{
    /**
     * Tamanho da pagina da lista de contas.
     */
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->value();
        $role = $request->string('role')->value();
        $status = $request->string('status')->value();

        $users = $this->query($search, $role, $status)
            // As assinaturas vem junto com o plano: sem isso a lista faria uma
            // consulta por conta para descobrir o plano. Quem decide qual vale
            // e o `isCurrent()` do modelo, e nao um SQL paralelo ao dele.
            ->with('subscriptions.plan')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $free = Plan::free()->name;

        return Inertia::render('admin/Users', [
            'users' => $users->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'isAdmin' => $user->isAdmin(),
                'suspended' => $user->isSuspended(),
                'verified' => $user->email_verified_at !== null,
                'plan' => $user->subscriptions
                    ->first(fn (Subscription $item): bool => $item->isCurrent())
                    ?->plan->name ?? $free,
                'createdAt' => Format::date($user->created_at),
            ]),
            'filters' => [
                'q' => $search,
                'role' => $role,
                'status' => $status,
            ],
            'total' => $users->total(),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $subscription = $user->activeSubscription();
        $quota = Quota::for($user);
        $actor = $request->user();

        return Inertia::render('admin/UserDetail', [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'isAdmin' => $user->isAdmin(),
                'suspended' => $user->isSuspended(),
                'suspendedAt' => Format::dateTime($user->suspended_at),
                'verified' => $user->email_verified_at !== null,
                'verifiedAt' => Format::dateTime($user->email_verified_at),
                'locale' => $user->locale,
                'hasPassword' => $user->hasPassword(),
                'createdAt' => Format::dateTime($user->created_at),
            ],
            // O que a tela pode oferecer: as travas da policy decidem, e nao
            // a tela (ADR-025).
            'can' => [
                'manage' => $actor instanceof User && $actor->can('manage', $user),
                'changeRole' => $actor instanceof User && $actor->can('changeRole', $user),
                'impersonate' => $actor instanceof User && $actor->can('impersonate', $user),
            ],
            'subscription' => $this->subscription($subscription),
            'quota' => [
                'plan' => $quota->plan->name,
                'unit' => $quota->unit(),
                'limit' => $quota->limit(),
                // Consumo cru: uma concessao deixa o numero negativo, e o
                // painel mostra como esta em vez de arredondar a favor.
                'used' => $quota->used(),
                'remaining' => $quota->remaining(),
                'exhausted' => $quota->exhausted(),
                'windowStart' => Format::date($quota->windowStart()),
                'windowEnd' => Format::date($quota->windowEnd()),
            ],
            'socialAccounts' => $user->socialAccounts()
                ->get()
                ->map(fn (SocialAccount $account): array => [
                    'provider' => $account->provider,
                    'createdAt' => Format::date($account->created_at),
                ])
                ->all(),
            'sessions' => $this->sessions($user),
            'payments' => $user->payments()
                ->latest('paid_at')
                ->limit(20)
                ->get()
                ->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'amount' => Format::money($payment->amount_cents, $payment->currency),
                    'method' => $payment->method->value,
                    'status' => $payment->status->value,
                    'paidAt' => Format::dateTime($payment->paid_at),
                    'note' => $payment->note,
                ])
                ->all(),
            'plans' => Plan::query()->active()->orderBy('sort')->get()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'interval' => $plan->interval,
                    'isFree' => $plan->isFree(),
                ])
                ->all(),
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

    /**
     * Sessoes abertas da conta (driver `database`). Em teste o driver e o
     * `array` e a tabela vem vazia - a lista e so leitura, nao ha acao aqui.
     *
     * @return list<array<string, mixed>>
     */
    private function sessions(User $user): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        $rows = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->limit(10)
            ->get();

        $sessions = [];

        foreach ($rows as $row) {
            // A tabela de sessoes nao tem model: o registro vira array aqui.
            $data = (array) $row;

            $ip = $data['ip_address'] ?? null;
            $agent = $data['user_agent'] ?? null;
            $activity = $data['last_activity'] ?? null;

            $sessions[] = [
                'ip' => is_string($ip) ? $ip : null,
                'agent' => is_string($agent) ? $agent : null,
                'lastActivity' => is_numeric($activity)
                    ? Format::dateTime(Carbon::createFromTimestamp((int) $activity))
                    : null,
            ];
        }

        return $sessions;
    }

    /**
     * @return Builder<User>
     */
    private function query(string $search, string $role, string $status): Builder
    {
        return User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when(UserRole::tryFrom($role) !== null, function (Builder $query) use ($role): void {
                $query->where('role', $role);
            })
            ->when($status === 'suspended', fn (Builder $query) => $query->whereNotNull('suspended_at'))
            ->when($status === 'active', fn (Builder $query) => $query->whereNull('suspended_at'))
            ->when($status === 'unverified', fn (Builder $query) => $query->whereNull('email_verified_at'));
    }
}
