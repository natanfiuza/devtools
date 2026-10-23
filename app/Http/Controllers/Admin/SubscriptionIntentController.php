<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ConfirmIntentPayment;
use App\Enums\AdminActionType;
use App\Enums\SubscriptionIntentStatus;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionIntent;
use App\Models\User;
use App\Support\DecimalInput;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fila de intencoes de assinatura Pro no painel administrativo (ADR-033).
 *
 * Lista os pedidos enviados pelo formulario `/subscribe`, permite filtrar por
 * estado (`pending`, `fulfilled`, `canceled`) e marcar cada pedido como
 * atendido ou cancelado com registro em `admin_actions`.
 */
class SubscriptionIntentController extends Controller
{
    use RecordsAdminActions;

    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $status = $request->string('status')->value();

        $intents = SubscriptionIntent::query()
            ->with(['user', 'plan'])
            ->when(
                in_array($status, ['pending', 'fulfilled', 'canceled'], true),
                fn (Builder $query) => $query->where('status', $status),
            )
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/Intents', [
            'intents' => $intents->through(fn (SubscriptionIntent $intent): array => [
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
            ]),
            'filters' => [
                'status' => $status,
            ],
            'total' => $intents->total(),
        ]);
    }

    public function fulfill(Request $request, SubscriptionIntent $intent): RedirectResponse
    {
        if ($intent->status === SubscriptionIntentStatus::Fulfilled) {
            return $this->toast('info', __('admin.toasts.intent_already_fulfilled'));
        }

        $intent->forceFill([
            'status' => SubscriptionIntentStatus::Fulfilled,
            'handled_by' => $request->user()?->id,
            'handled_at' => now(),
        ])->save();

        $this->record($request, AdminActionType::SubscriptionIntentFulfilled, $intent, [
            'plan' => $intent->plan->slug,
            'username' => $intent->username,
        ]);

        return $this->toast('success', __('admin.toasts.intent_fulfilled'));
    }

    /**
     * "Recebi o dinheiro": registra o pagamento, concede o plano vinculado a
     * ele e marca a intencao como atendida, numa transacao so (ADR-054).
     */
    public function confirmPayment(
        Request $request,
        SubscriptionIntent $intent,
        ConfirmIntentPayment $confirm,
    ): RedirectResponse {
        if ($intent->status !== SubscriptionIntentStatus::Pending) {
            return $this->toast('info', __('admin.toasts.intent_not_pending'));
        }

        $request->merge(['amount' => DecimalInput::normalize($request->input('amount'))]);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $paidAt = $validated['paid_at'] ?? null;
        $note = $validated['note'] ?? null;
        $admin = $request->user();

        $payment = $confirm(
            intent: $intent,
            amountCents: DecimalInput::toCents($validated['amount'] ?? null),
            handledBy: $admin instanceof User ? $admin : null,
            paidAt: is_string($paidAt) ? Carbon::parse($paidAt) : null,
            note: is_string($note) ? $note : null,
        );

        $this->record($request, AdminActionType::SubscriptionIntentPaymentConfirmed, $intent, [
            'plan' => $intent->plan->slug,
            'username' => $intent->username,
            'pagamento' => $payment->getKey(),
            'valor' => $payment->amount_cents,
        ]);

        return $this->toast('success', __('admin.toasts.intent_payment_confirmed', [
            'plan' => $intent->plan->name,
        ]));
    }

    public function cancel(Request $request, SubscriptionIntent $intent): RedirectResponse
    {
        if ($intent->status === SubscriptionIntentStatus::Canceled) {
            return $this->toast('info', __('admin.toasts.intent_already_canceled'));
        }

        $intent->forceFill([
            'status' => SubscriptionIntentStatus::Canceled,
            'handled_by' => $request->user()?->id,
            'handled_at' => now(),
        ])->save();

        $this->record($request, AdminActionType::SubscriptionIntentCanceled, $intent, [
            'plan' => $intent->plan->slug,
            'username' => $intent->username,
        ]);

        return $this->toast('success', __('admin.toasts.intent_canceled'));
    }

    private function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
