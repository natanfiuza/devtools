<?php

namespace App\Actions\Payments;

use App\Actions\Donations\ConfirmDonation;
use App\Contracts\Payments\PaymentGateway;
use App\Enums\CheckoutMode;
use App\Enums\CheckoutStatus;
use App\Enums\DonationStatus;
use App\Enums\WebhookEventStatus;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\CheckoutSession;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Support\Payments\GatewayPayment;

/**
 * Executa o efeito local de uma notificacao ja guardada (F6).
 *
 * O corpo do webhook so aponta para o recurso; o estado real vem sempre de uma
 * consulta a API do provedor. Cada topico tem um efeito pequeno e idempotente:
 *
 * - `payment`: aprovado abre periodo (checkout avulso) ou sincroniza estorno;
 *   pendente/rejeitado nao libera nada;
 * - `subscription_preapproval`: autorizar **nao** libera cota - so marca a
 *   sessao como autorizada e para o relogio de expiracao; cancelar encerra a
 *   renovacao;
 * - `subscription_authorized_payment`: aprovado segue o mesmo caminho de um
 *   pagamento e empilha o periodo da renovacao; recusado dispara o aviso de
 *   falha uma vez por periodo.
 *
 * Pagamento de doacao (referencia `don_<ulid>`) tem efeito proprio: confirmar
 * uma vez, sem liberar cota, ou sincronizar estorno/falha. Topico desconhecido
 * e `ignored`.
 */
final class HandleWebhookEvent
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApproveGatewayPayment $approvePayment,
        private readonly RefundGatewayPayment $refundPayment,
        private readonly HandleRenewalFailure $handleRenewalFailure,
        private readonly ConfirmDonation $confirmDonation,
    ) {}

    public function __invoke(WebhookEvent $event): WebhookEventStatus
    {
        $resourceId = $event->resource_id;

        if (! is_string($resourceId) || $resourceId === '') {
            return WebhookEventStatus::Ignored;
        }

        return match ($event->topic) {
            'payment' => $this->payment($resourceId),
            'subscription_preapproval' => $this->preapproval($resourceId),
            'subscription_authorized_payment' => $this->authorizedPayment($resourceId),
            default => WebhookEventStatus::Ignored,
        };
    }

    private function payment(string $resourceId): WebhookEventStatus
    {
        $payment = $this->gateway->payment($resourceId);

        if ($payment->isRefunded()) {
            return $this->syncRefund($payment);
        }

        $donation = $this->donationFor($payment);

        if ($donation instanceof Donation) {
            return $this->donationPayment($donation, $payment);
        }

        if (! $payment->isApproved()) {
            // Pendente ou recusado: nada entra em `payments` nem libera cota.
            return WebhookEventStatus::Ignored;
        }

        $registered = ($this->approvePayment)($payment);

        return $registered instanceof Payment
            ? WebhookEventStatus::Processed
            : WebhookEventStatus::Ignored;
    }

    /**
     * Doacao aprovada, recusada ou ainda pendente.
     *
     * Aprovada confirma uma vez (valor e moeda conferidos com a doacao); Pix
     * recusado/cancelado encerra a doacao como falha. Pendente nao faz nada.
     */
    private function donationPayment(Donation $donation, GatewayPayment $payment): WebhookEventStatus
    {
        if ($payment->isApproved()) {
            $confirmed = ($this->confirmDonation)(
                $donation,
                $payment->amountCents,
                $payment->currency,
                $payment->paidAt,
            );

            return $confirmed ? WebhookEventStatus::Processed : WebhookEventStatus::Ignored;
        }

        if (in_array($payment->status, ['rejected', 'cancelled'], true)) {
            if ($donation->status === DonationStatus::Pending) {
                $donation->forceFill(['status' => DonationStatus::Failed])->save();
            }

            return WebhookEventStatus::Processed;
        }

        return WebhookEventStatus::Ignored;
    }

    private function preapproval(string $resourceId): WebhookEventStatus
    {
        $preapproval = $this->gateway->preapproval($resourceId);

        $session = CheckoutSession::query()
            ->where('gateway', 'mercadopago')
            ->where('mode', CheckoutMode::Recurring->value)
            ->where('gateway_ref', $preapproval->id)
            ->orderByDesc('id')
            ->first();

        if (! $session instanceof CheckoutSession) {
            return WebhookEventStatus::Ignored;
        }

        if ($preapproval->isAuthorized()) {
            // A autorizacao nao abre periodo: o primeiro pagamento aprovado
            // pode levar cerca de uma hora e e ele quem libera a cota.
            $session->forceFill([
                'authorized_at' => $session->authorized_at ?? now(),
                'expires_at' => null,
            ])->save();

            return WebhookEventStatus::Processed;
        }

        if ($preapproval->isCanceled()) {
            if ($session->status->isOpen()) {
                $session->forceFill(['status' => CheckoutStatus::Canceled])->save();
            }

            Subscription::query()
                ->where('gateway', 'mercadopago')
                ->where('gateway_ref', $preapproval->id)
                ->whereNull('renewal_canceled_at')
                ->update(['renewal_canceled_at' => now()]);

            return WebhookEventStatus::Processed;
        }

        return WebhookEventStatus::Ignored;
    }

    private function authorizedPayment(string $resourceId): WebhookEventStatus
    {
        $authorized = $this->gateway->authorizedPayment($resourceId);

        if ($authorized->isApproved()) {
            $paymentId = $authorized->paymentId;

            if (! is_string($paymentId) || $paymentId === '') {
                throw PaymentGatewayException::invalidResponse('authorized_payment.payment');
            }

            $payment = $this->gateway->payment($paymentId);
            $registered = ($this->approvePayment)($payment, $authorized->preapprovalId);

            return $registered instanceof Payment
                ? WebhookEventStatus::Processed
                : WebhookEventStatus::Ignored;
        }

        if (in_array($authorized->status, ['rejected', 'cancelled', 'canceled'], true)) {
            ($this->handleRenewalFailure)($authorized->preapprovalId);

            return WebhookEventStatus::Processed;
        }

        // `scheduled`, `pending`, `in_process`: o provedor ainda vai tentar.
        return WebhookEventStatus::Ignored;
    }

    private function syncRefund(GatewayPayment $payment): WebhookEventStatus
    {
        $local = Payment::query()
            ->where('gateway', 'mercadopago')
            ->where('gateway_ref', $payment->id)
            ->first();

        if (! $local instanceof Payment) {
            // Pode ser doacao estornada: nao ha `payments`, so a doacao.
            $donation = $this->donationFor($payment);

            if (! $donation instanceof Donation) {
                return WebhookEventStatus::Ignored;
            }

            if ($donation->status !== DonationStatus::Refunded) {
                $donation->forceFill(['status' => DonationStatus::Refunded])->save();
            }

            return WebhookEventStatus::Processed;
        }

        // O dinheiro ja voltou: so sincroniza o estado local e tenta cancelar
        // a preapproval, sem chamar a API de estorno de novo.
        ($this->refundPayment)($local, notifyGateway: false);

        return WebhookEventStatus::Processed;
    }

    /**
     * A doacao daquele pagamento: pela referencia `don_<ulid>` ou, se ela
     * faltar, pelo id do pagamento gravado na criacao da cobranca.
     */
    private function donationFor(GatewayPayment $payment): ?Donation
    {
        $reference = $payment->externalReference;

        if (is_string($reference) && preg_match('/^don_([0-9A-HJKMNP-TV-Z]{26})$/', $reference, $matches) === 1) {
            $donation = Donation::query()->where('ulid', $matches[1])->first();

            if ($donation instanceof Donation) {
                return $donation;
            }
        }

        return Donation::query()
            ->where('gateway', 'mercadopago')
            ->where('gateway_ref', $payment->id)
            ->first();
    }
}
