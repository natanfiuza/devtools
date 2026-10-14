<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RefundPayment;
use App\Actions\Admin\RegisterPayment;
use App\Enums\AdminActionType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagamentos: a lista e o registro manual (ADR-027).
 *
 * O gateway entra na F6; ate la, quem recebe por pix ou transferencia registra
 * aqui. E o unico lugar do sistema onde dinheiro entra sem passar por um
 * provedor, entao tudo o que se registra sai com `gateway = manual`.
 */
class PaymentController extends Controller
{
    use RecordsAdminActions;

    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->value();
        $status = $request->string('status')->value();

        $payments = $this->query($search, $status)
            ->with(['user', 'plan'])
            ->orderByDesc('paid_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/Payments', [
            'payments' => $payments->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'account' => $payment->user->email,
                'plan' => $payment->plan?->name,
                'amount' => Format::money($payment->amount_cents, $payment->currency),
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'paidAt' => Format::dateTime($payment->paid_at),
                'note' => $payment->note,
            ]),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'total' => $payments->total(),
            'options' => [
                'plans' => Plan::query()->active()->orderBy('sort')->get()
                    ->map(fn (Plan $plan): array => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'price' => Format::money($plan->price_cents, $plan->currency),
                    ])
                    ->all(),
                'methods' => array_map(
                    fn (PaymentMethod $method): string => $method->value,
                    PaymentMethod::cases(),
                ),
            ],
        ]);
    }

    /**
     * Registra um pagamento recebido por fora do site. Conceder o plano junto
     * e opcional: cobranca e acesso sao duas coisas.
     */
    public function store(Request $request, RegisterPayment $register): RedirectResponse
    {
        // O valor chega como a pessoa escreve na moeda dela - `29,90` aqui,
        // `29.90` em ingles -, e a regra `numeric` so entende o ponto. A
        // conversao acontece antes de validar, para o numero que a tela mostra
        // ser o numero que entra.
        $request->merge(['amount' => $this->toDecimal($request->input('amount'))]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'plan' => ['nullable', 'integer', 'exists:plans,id'],
            // O admin digita o valor em moeda (29,90) e o banco guarda centavos:
            // inteiro em centavo e o formato que nao perde precisao.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'method' => ['required', 'string', 'in:'.implode(',', array_map(
                fn (PaymentMethod $method): string => $method->value,
                PaymentMethod::cases(),
            ))],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'grant_plan' => ['boolean'],
        ]);

        $email = is_string($validated['email'] ?? null) ? $validated['email'] : '';

        $user = User::query()->where('email', $email)->firstOrFail();

        $planId = $validated['plan'] ?? null;
        $plan = is_numeric($planId) ? Plan::query()->find((int) $planId) : null;

        $method = PaymentMethod::from(
            is_string($validated['method'] ?? null) ? $validated['method'] : PaymentMethod::Pix->value,
        );

        $paidAt = $validated['paid_at'] ?? null;
        $note = $validated['note'] ?? null;

        $amount = $validated['amount'] ?? null;

        $payment = $register(
            user: $user,
            amountCents: is_numeric($amount) ? (int) round(((float) $amount) * 100) : 0,
            method: $method,
            plan: $plan,
            paidAt: is_string($paidAt) ? Carbon::parse($paidAt) : null,
            note: is_string($note) ? $note : null,
            grantPlan: $request->boolean('grant_plan'),
        );

        $this->record($request, AdminActionType::PaymentRegistered, $payment, [
            'conta' => $user->email,
            'valor' => $payment->amount_cents,
            'plano' => $plan?->slug,
        ]);

        return $this->back('success', __('admin.toasts.payment_registered'));
    }

    /**
     * Estorna: o dinheiro voltou e a assinatura que o pagamento abriu acaba.
     */
    public function refund(Request $request, Payment $payment, RefundPayment $refund): RedirectResponse
    {
        if ($payment->status === PaymentStatus::Refunded) {
            return $this->back('info', __('admin.toasts.payment_already_refunded'));
        }

        $refund($payment);

        $this->record($request, AdminActionType::PaymentRefunded, $payment, [
            'valor' => $payment->amount_cents,
        ]);

        return $this->back('success', __('admin.toasts.payment_refunded'));
    }

    /**
     * @return Builder<Payment>
     */
    private function query(string $search, string $status): Builder
    {
        return Payment::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('user', function (Builder $query) use ($search): void {
                    $query->where('email', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->when(
                PaymentStatus::tryFrom($status) !== null,
                fn (Builder $query) => $query->where('status', $status),
            );
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }

    /**
     * Deixa o valor em ponto decimal.
     *
     * O separador decimal e o ultimo sinal que aparece no numero: `29,90` e
     * `29.90` viram os dois `29.90`, e `1.234,56` vira `1234.56`. Quem escreve
     * com separador de milhar digita os dois, como em qualquer calculadora -
     * `2.990` sozinho continua sendo lido como o numero que esta escrito.
     *
     * O que nao der para ler assim segue como veio, e a validacao recusa: valor
     * de dinheiro nao se adivinha.
     */
    private function toDecimal(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');

        if ($lastDot === false && $lastComma === false) {
            return $value;
        }

        $decimal = $lastDot > $lastComma ? '.' : ',';
        $thousands = $decimal === '.' ? ',' : '.';

        // Primeiro o milhar sai, depois o separador decimal vira ponto: na
        // ordem inversa o ponto recem-criado seria lido como milhar.
        return str_replace($decimal, '.', str_replace($thousands, '', $value));
    }
}
