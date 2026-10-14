<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Format;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visao geral do painel: quatro numeros e as ultimas contas.
 *
 * Sem grafico de proposito (DESIGN): o que se olha aqui e "quantas contas",
 * "quanto entrou no mes" e "quem acabou de chegar" - e as ultimas contas sao
 * a unica coisa acionavel da tela.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'accounts' => User::query()->count(),
                // Assinantes pagantes agora: assinatura vigente e nao vencida.
                'subscribers' => Subscription::query()->current()->count(),
                'revenue' => $this->revenueThisMonth(),
                'suspended' => User::query()->whereNotNull('suspended_at')->count(),
            ],
            'recent' => User::query()
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'createdAt' => Format::dateTime($user->created_at),
                    'suspended' => $user->isSuspended(),
                ])
                ->all(),
        ]);
    }

    /**
     * Receita do mes corrente: pagamentos pagos e ainda nao estornados (o
     * estorno sai da conta porque o dinheiro voltou).
     *
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
}
