<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\CheckoutMode;
use App\Enums\CheckoutStatus;
use App\Exceptions\Payments\CheckoutException;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Payments\CheckoutRequest;
use App\Support\Payments\PreapprovalRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Abre uma sessao de checkout pago (F6).
 *
 * A sessao nasce antes da chamada ao provedor porque o `ulid` dela e a
 * `external_reference` que volta no webhook - e o fio que liga "esta pessoa foi
 * pagar" a "esta pessoa pagou". Valor e moeda ficam congelados aqui; a
 * confirmacao compara o pago com esta foto, nunca com o preco atual.
 *
 * Nada e liberado nesta etapa: o periodo nasce so na aprovacao (webhook).
 */
final class StartCheckout
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CancelRecurring $cancelRecurring,
    ) {}

    /**
     * @throws CheckoutException quando a regra de produto recusa o inicio
     * @throws PaymentGatewayException quando o provedor falha na criacao
     */
    public function __invoke(User $user, Plan $plan, CheckoutMode $mode): CheckoutSession
    {
        if (! $this->gateway->isAvailable()) {
            throw CheckoutException::gatewayUnavailable();
        }

        if ($plan->isFree() || ! $plan->active || $plan->price_cents <= 0) {
            throw CheckoutException::planUnavailable();
        }

        if (! in_array($plan->interval, ['month', 'year'], true)) {
            throw CheckoutException::planUnavailable();
        }

        $recurring = $this->cancelRecurring->recurringSubscriptions($user);

        if ($mode === CheckoutMode::Recurring && $this->hasRecurringOnPlan($user, $plan, $recurring)) {
            throw CheckoutException::recurringActive();
        }

        // Qualquer preapproval ativa e cancelada antes de abrir o novo checkout:
        // pagar avulso no mesmo plano nao pode somar com a renovacao que viria,
        // e trocar de plano encerra a recorrencia antiga. Se o provedor recusar,
        // a excecao sobe e nada no banco muda.
        if ($recurring->isNotEmpty()) {
            ($this->cancelRecurring)($user);
        }

        $gateway = (string) config('payments.gateway', 'mercadopago');

        /** @var CheckoutSession $session */
        $session = CheckoutSession::create([
            'user_id' => $user->getKey(),
            'plan_id' => $plan->getKey(),
            'mode' => $mode,
            'amount_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'status' => CheckoutStatus::Pending,
            'gateway' => $gateway,
            'expires_at' => now()->addMinutes((int) config('payments.checkout.expires_minutes', 60)),
        ]);

        try {
            if ($mode === CheckoutMode::Recurring) {
                $this->createPreapproval($user, $plan, $session);
            } else {
                $this->createPreference($user, $plan, $session);
            }
        } catch (PaymentGatewayException $exception) {
            $session->forceFill(['status' => CheckoutStatus::Failed])->save();

            throw $exception;
        } catch (Throwable $exception) {
            // O provedor pode ter criado a cobranca; o estado local nao pode
            // ficar "pendente" sem `init_point` e sem explicacao.
            $session->forceFill(['status' => CheckoutStatus::Failed])->save();

            throw $exception;
        }

        return $session->refresh();
    }

    private function createPreference(User $user, Plan $plan, CheckoutSession $session): void
    {
        $preference = $this->gateway->createPreference(new CheckoutRequest(
            idempotencyKey: $this->externalReference($session),
            externalReference: $this->externalReference($session),
            title: $plan->name,
            amountCents: $session->amount_cents,
            currency: $session->currency,
            payerEmail: $user->email,
            notificationUrl: $this->webhookUrl(),
            backUrl: $this->backUrl($session),
            expiresAt: $session->expires_at,
            statementDescriptor: $this->statementDescriptor(),
        ));

        $session->forceFill([
            'gateway_ref' => $preference->id,
            'init_point' => $preference->initPoint,
        ])->save();
    }

    private function createPreapproval(User $user, Plan $plan, CheckoutSession $session): void
    {
        $preapproval = $this->gateway->createPreapproval(new PreapprovalRequest(
            idempotencyKey: $this->externalReference($session),
            externalReference: $this->externalReference($session),
            reason: $plan->name,
            amountCents: $session->amount_cents,
            currency: $session->currency,
            payerEmail: $user->email,
            frequency: $plan->interval === 'year' ? 12 : 1,
            frequencyType: 'months',
            notificationUrl: $this->webhookUrl(),
            backUrl: $this->backUrl($session),
        ));

        $session->forceFill([
            'gateway_ref' => $preapproval->id,
            'init_point' => $preapproval->initPoint,
        ])->save();
    }

    /**
     * Ja existe renovacao viva neste plano? Vale tanto a assinatura com
     * preapproval ativa quanto uma sessao de recorrencia ainda aberta (a
     * preapproval pode nao ter sido autorizada nem virado assinatura).
     *
     * @param  Collection<int, Subscription>  $recurring
     */
    private function hasRecurringOnPlan(User $user, Plan $plan, Collection $recurring): bool
    {
        $subscriptionActive = $recurring->contains(
            fn (Subscription $subscription): bool => $subscription->plan_id === $plan->getKey(),
        );

        if ($subscriptionActive) {
            return true;
        }

        return CheckoutSession::query()
            ->where('user_id', $user->getKey())
            ->where('plan_id', $plan->getKey())
            ->where('mode', CheckoutMode::Recurring->value)
            ->where('status', CheckoutStatus::Pending->value)
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function externalReference(CheckoutSession $session): string
    {
        return 'chk_'.$session->ulid;
    }

    private function webhookUrl(): ?string
    {
        $configured = config('payments.checkout.webhook_url');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        if (! Route::has('webhooks.mercadopago')) {
            return null;
        }

        $url = route('webhooks.mercadopago');

        if (! app()->runningUnitTests() && $this->isLoopbackUrl($url)) {
            return null;
        }

        return $url;
    }

    private function isLoopbackUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return true;
        }

        return in_array(mb_strtolower(trim($host, '[]')), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }

    private function backUrl(CheckoutSession $session): string
    {
        $configured = config('payments.checkout.back_url');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return route('subscribe.checkout.return', ['checkoutSession' => $session->ulid]);
    }

    private function statementDescriptor(): ?string
    {
        $descriptor = config('payments.checkout.statement_descriptor');

        return is_string($descriptor) && trim($descriptor) !== '' ? $descriptor : null;
    }
}
