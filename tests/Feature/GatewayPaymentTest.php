<?php

use App\Actions\Payments\ApproveGatewayPayment;
use App\Actions\Payments\HandleWebhookEvent;
use App\Enums\CheckoutMode;
use App\Enums\CheckoutStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WebhookEventStatus;
use App\Exceptions\Payments\PaymentVerificationException;
use App\Jobs\ProcessWebhookEvent;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\PaymentRenewalFailed;
use App\Support\Payments\GatewayPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'payments.mercadopago.access_token' => 'token-de-teste',
        'payments.mercadopago.base_url' => 'https://api.mercadopago.com',
        'payments.mercadopago.webhook_secret' => 'segredo-de-teste',
    ]);

    Http::preventStrayRequests();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function payloadDePagamentoMp(array $overrides = []): array
{
    return array_merge([
        'id' => 'pay_1',
        'status' => 'approved',
        'status_detail' => 'accredited',
        'transaction_amount' => 8.9,
        'currency_id' => 'BRL',
        'external_reference' => null,
        'payment_type_id' => 'credit_card',
        'payment_method_id' => 'visa',
        'date_approved' => now()->toIso8601String(),
    ], $overrides);
}

function dtoDePagamentoGateway(string $id, string $reference, int $amountCents = 890, string $currency = 'BRL'): GatewayPayment
{
    return new GatewayPayment(
        id: $id,
        status: 'approved',
        statusDetail: 'accredited',
        amountCents: $amountCents,
        currency: $currency,
        externalReference: $reference,
        method: PaymentMethod::CreditCard,
        methodId: 'visa',
        paidAt: now(),
    );
}

/**
 * @return array{0: User, 1: Plan, 2: CheckoutSession}
 */
function checkoutDeTeste(string $mode = 'one_time', int $amountCents = 890): array
{
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price_cents' => $amountCents, 'currency' => 'BRL']);

    $session = CheckoutSession::factory()->for($user)->for($plan)->create([
        'mode' => $mode === 'recurring' ? CheckoutMode::Recurring : CheckoutMode::OneTime,
        'amount_cents' => $amountCents,
        'currency' => 'BRL',
        'gateway' => 'mercadopago',
        'gateway_ref' => $mode === 'recurring' ? 'pre_123' : 'pref_123',
    ]);

    return [$user, $plan, $session];
}

it('aprova o checkout avulso e abre um periodo pago', function () {
    [$user, $plan, $session] = checkoutDeTeste();

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_1' => Http::response(payloadDePagamentoMp([
            'external_reference' => 'chk_'.$session->ulid,
        ])),
    ]);

    $event = WebhookEvent::factory()->create(['topic' => 'payment', 'resource_id' => 'pay_1']);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);

    $payment = Payment::query()->sole();
    $subscription = Subscription::query()->sole();

    expect($payment->user_id)->toBe($user->id)
        ->and($payment->plan_id)->toBe($plan->id)
        ->and($payment->subscription_id)->toBe($subscription->id)
        ->and($payment->amount_cents)->toBe(890)
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->gateway)->toBe('mercadopago')
        ->and($payment->gateway_ref)->toBe('pay_1')
        ->and($payment->gateway_method)->toBe('visa')
        ->and($subscription->gateway)->toBe('mercadopago')
        ->and($subscription->gateway_ref)->toBeNull()
        ->and($subscription->isCurrent())->toBeTrue()
        ->and($user->fresh()->effectivePlan()->id)->toBe($plan->id);

    $session->refresh();

    expect($session->status)->toBe(CheckoutStatus::Approved)
        ->and($session->gateway_payment_id)->toBe('pay_1')
        ->and($session->payment_id)->toBe($payment->id)
        ->and($session->subscription_id)->toBe($subscription->id)
        ->and($session->approved_at)->not->toBeNull();
});

it('empilha o periodo da renovacao na preapproval', function () {
    [$user, , $session] = checkoutDeTeste('recurring');

    $first = app(ApproveGatewayPayment::class)(
        dtoDePagamentoGateway('pay_1', 'chk_'.$session->ulid),
        'pre_123',
    );

    $firstSubscription = $first?->subscription;

    $second = app(ApproveGatewayPayment::class)(
        dtoDePagamentoGateway('pay_2', 'chk_'.$session->ulid),
        'pre_123',
    );

    $rows = $user->subscriptions()->orderBy('current_period_start')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->gateway_ref)->toBe('pre_123')
        ->and($rows[1]->gateway_ref)->toBe('pre_123')
        ->and($rows[1]->current_period_start?->toDateTimeString())
        ->toBe($firstSubscription?->current_period_end?->toDateTimeString())
        ->and(Payment::query()->count())->toBe(2)
        ->and($session->refresh()->subscription_id)->toBe($rows[1]->id);
});

it('nao duplica pagamento nem periodo quando o mesmo pagamento chega de novo', function () {
    [, , $session] = checkoutDeTeste('recurring');

    $dto = dtoDePagamentoGateway('pay_1', 'chk_'.$session->ulid);

    $first = app(ApproveGatewayPayment::class)($dto, 'pre_123');
    $second = app(ApproveGatewayPayment::class)($dto, 'pre_123');

    expect($first?->id)->toBe($second?->id)
        ->and(Payment::query()->count())->toBe(1)
        ->and(Subscription::query()->count())->toBe(1);
});

it('recusa valor ou moeda divergentes sem liberar nada', function (array $override) {
    [, , $session] = checkoutDeTeste();

    $payload = payloadDePagamentoMp(array_merge([
        'external_reference' => 'chk_'.$session->ulid,
    ], $override));

    Http::fake(['api.mercadopago.com/v1/payments/pay_1' => Http::response($payload)]);

    $event = WebhookEvent::factory()->create(['topic' => 'payment', 'resource_id' => 'pay_1']);

    expect(fn () => app(HandleWebhookEvent::class)($event))
        ->toThrow(PaymentVerificationException::class);

    expect(Payment::query()->count())->toBe(0)
        ->and(Subscription::query()->count())->toBe(0)
        ->and($session->refresh()->status)->toBe(CheckoutStatus::Pending);
})->with([
    'valor' => [['transaction_amount' => 1.0]],
    'moeda' => [['currency_id' => 'ARS']],
]);

it('recusa cobranca de outra preapproval', function () {
    [, , $session] = checkoutDeTeste('recurring');

    expect(fn () => app(ApproveGatewayPayment::class)(
        dtoDePagamentoGateway('pay_1', 'chk_'.$session->ulid),
        'pre_outra',
    ))->toThrow(PaymentVerificationException::class);

    expect(Payment::query()->count())->toBe(0);
});

it('ignora pagamento aprovado sem checkout correspondente', function () {
    expect(app(ApproveGatewayPayment::class)(dtoDePagamentoGateway('pay_1', 'chk_01HZZZZZZZZZZZZZZZZZZZZZZZ')))
        ->toBeNull();
});

it('nao libera cota na autorizacao da preapproval', function () {
    [$user, , $session] = checkoutDeTeste('recurring');

    Http::fake([
        'api.mercadopago.com/preapproval/pre_123' => Http::response([
            'id' => 'pre_123',
            'status' => 'authorized',
            'external_reference' => 'chk_'.$session->ulid,
            'auto_recurring' => ['transaction_amount' => 8.9, 'currency_id' => 'BRL'],
        ]),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'subscription_preapproval',
        'resource_id' => 'pre_123',
    ]);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);

    $session->refresh();

    expect($session->status)->toBe(CheckoutStatus::Pending)
        ->and($session->authorized_at)->not->toBeNull()
        ->and($session->expires_at)->toBeNull()
        ->and($user->subscriptions()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

it('encerra a renovacao quando a preapproval e cancelada', function () {
    [$user, $plan, $session] = checkoutDeTeste('recurring');

    $subscription = Subscription::factory()->for($user)->for($plan)->create([
        'gateway' => 'mercadopago',
        'gateway_ref' => 'pre_123',
        'status' => SubscriptionStatus::Active,
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    Http::fake([
        'api.mercadopago.com/preapproval/pre_123' => Http::response([
            'id' => 'pre_123',
            'status' => 'canceled',
            'external_reference' => 'chk_'.$session->ulid,
        ]),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'subscription_preapproval',
        'resource_id' => 'pre_123',
    ]);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);

    expect($session->refresh()->status)->toBe(CheckoutStatus::Canceled)
        ->and($subscription->refresh()->renewal_canceled_at)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);
});

it('aprova a primeira cobranca autorizada da preapproval', function () {
    [$user, , $session] = checkoutDeTeste('recurring');

    Http::fake([
        'api.mercadopago.com/authorized_payments/ap_1' => Http::response([
            'id' => 'ap_1',
            'preapproval_id' => 'pre_123',
            'payment' => [
                'id' => 'pay_1',
                'status' => 'approved',
                'transaction_amount' => 8.9,
                'currency_id' => 'BRL',
            ],
        ]),
        'api.mercadopago.com/v1/payments/pay_1' => Http::response(payloadDePagamentoMp([
            'id' => 'pay_1',
            'external_reference' => 'chk_'.$session->ulid,
        ])),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'subscription_authorized_payment',
        'resource_id' => 'ap_1',
    ]);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);

    expect(Payment::query()->count())->toBe(1)
        ->and(Subscription::query()->sole()->gateway_ref)->toBe('pre_123')
        ->and($user->fresh()->effectivePlan()->isFree())->toBeFalse()
        ->and($session->refresh()->status)->toBe(CheckoutStatus::Approved);
});

it('avisa a falha de renovacao uma vez por periodo', function () {
    Notification::fake();

    [$user, $plan] = checkoutDeTeste('recurring');

    $subscription = Subscription::factory()->for($user)->for($plan)->create([
        'gateway' => 'mercadopago',
        'gateway_ref' => 'pre_123',
        'status' => SubscriptionStatus::Active,
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    Http::fake([
        'api.mercadopago.com/authorized_payments/*' => Http::response([
            'id' => 'ap_1',
            'preapproval_id' => 'pre_123',
            'payment' => ['id' => 'pay_1', 'status' => 'rejected'],
        ]),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'subscription_authorized_payment',
        'resource_id' => 'ap_1',
    ]);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);
    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);

    expect($subscription->refresh()->renewal_failed_at)->not->toBeNull();

    Notification::assertSentTimes(PaymentRenewalFailed::class, 1);
    Notification::assertSentTo($user, PaymentRenewalFailed::class);
});

it('limpa a falha quando a renovacao e aprovada depois', function () {
    [$user, , $session] = checkoutDeTeste('recurring');

    $subscription = Subscription::factory()->for($user)->for($session->plan)->create([
        'gateway' => 'mercadopago',
        'gateway_ref' => 'pre_123',
        'status' => SubscriptionStatus::Active,
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
        'renewal_failed_at' => now()->subDay(),
    ]);

    app(ApproveGatewayPayment::class)(
        dtoDePagamentoGateway('pay_1', 'chk_'.$session->ulid),
        'pre_123',
    );

    expect($subscription->refresh()->renewal_failed_at)->toBeNull();
});

it('sincroniza estorno vindo do webhook sem chamar a API de estorno', function () {
    [$user, $plan, $session] = checkoutDeTeste('recurring');

    $subscription = Subscription::factory()->for($user)->for($plan)->create([
        'gateway' => 'mercadopago',
        'gateway_ref' => 'pre_123',
        'status' => SubscriptionStatus::Active,
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    $payment = Payment::factory()->for($user)->for($plan)->create([
        'subscription_id' => $subscription->id,
        'amount_cents' => 890,
        'gateway' => 'mercadopago',
        'gateway_ref' => 'pay_1',
        'status' => PaymentStatus::Paid,
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_1' => Http::response(payloadDePagamentoMp([
            'id' => 'pay_1',
            'status' => 'refunded',
            'external_reference' => 'chk_'.$session->ulid,
        ])),
        'api.mercadopago.com/preapproval/pre_123' => Http::response(['id' => 'pre_123', 'status' => 'canceled']),
    ]);

    $event = WebhookEvent::factory()->create(['topic' => 'payment', 'resource_id' => 'pay_1']);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Canceled);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/refunds'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.mercadopago.com/preapproval/pre_123');
});

it('nao libera nada para pagamento pendente ou rejeitado', function (string $status) {
    [, , $session] = checkoutDeTeste();

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_1' => Http::response(payloadDePagamentoMp([
            'status' => $status,
            'external_reference' => 'chk_'.$session->ulid,
        ])),
    ]);

    $event = WebhookEvent::factory()->create(['topic' => 'payment', 'resource_id' => 'pay_1']);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Ignored);

    expect(Payment::query()->count())->toBe(0)
        ->and(Subscription::query()->count())->toBe(0);
})->with(['pending', 'rejected']);

it('marca o evento como falho e guarda o erro quando a aprovacao nao casa', function () {
    [, , $session] = checkoutDeTeste();

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_1' => Http::response(payloadDePagamentoMp([
            'transaction_amount' => 1.0,
            'external_reference' => 'chk_'.$session->ulid,
        ])),
    ]);

    $event = WebhookEvent::factory()->create(['topic' => 'payment', 'resource_id' => 'pay_1']);

    $job = new ProcessWebhookEvent((int) $event->getKey());

    expect(fn () => $job->handle(app(HandleWebhookEvent::class)))
        ->toThrow(PaymentVerificationException::class);

    $event->refresh();

    expect($event->status)->toBe(WebhookEventStatus::Failed)
        ->and($event->attempts)->toBe(1)
        ->and($event->error)->toContain('valor');
});

it('nao reprocessa evento ja concluido', function () {
    $event = WebhookEvent::factory()->processed()->create(['topic' => 'payment', 'resource_id' => 'pay_1']);

    $job = new ProcessWebhookEvent((int) $event->getKey());
    $job->handle(app(HandleWebhookEvent::class));

    Http::assertNothingSent();

    expect($event->refresh()->attempts)->toBe(0);
});
