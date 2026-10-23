<?php

namespace App\Actions\Admin;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionIntentStatus;
use App\Models\Payment;
use App\Models\SubscriptionIntent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Confirma o pagamento de uma intencao de assinatura (ADR-054).
 *
 * E o "recebi o dinheiro" do admin: numa transacao so, registra o pagamento,
 * concede o plano pedido vinculado a ele (o `RegisterPayment` faz isso para
 * plano pago) e marca a intencao como atendida. O vinculo pagamento ->
 * assinatura e o que faz o estorno encerrar o acesso depois.
 */
final class ConfirmIntentPayment
{
    public function __construct(private readonly RegisterPayment $registerPayment) {}

    public function __invoke(
        SubscriptionIntent $intent,
        int $amountCents,
        ?User $handledBy = null,
        ?CarbonInterface $paidAt = null,
        ?string $note = null,
    ): Payment {
        if ($intent->status !== SubscriptionIntentStatus::Pending) {
            throw new InvalidArgumentException('Only pending intents can be confirmed.');
        }

        $intent->loadMissing(['user', 'plan']);

        return DB::transaction(function () use ($intent, $amountCents, $handledBy, $paidAt, $note): Payment {
            $payment = ($this->registerPayment)(
                user: $intent->user,
                amountCents: $amountCents,
                method: PaymentMethod::from($intent->payment_method->value),
                plan: $intent->plan,
                paidAt: $paidAt,
                note: $note,
            );

            $intent->forceFill([
                'status' => SubscriptionIntentStatus::Fulfilled,
                'handled_by' => $handledBy?->getKey(),
                'handled_at' => now(),
            ])->save();

            return $payment;
        });
    }
}
