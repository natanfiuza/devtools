<?php

use App\Enums\AdminActionType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AdminAction;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'payments.mercadopago.access_token' => 'token-de-teste',
        'payments.mercadopago.base_url' => 'https://api.mercadopago.com',
    ]);

    Http::preventStrayRequests();
});

/**
 * @return array{0: User, 1: Plan, 2: Subscription, 3: Payment}
 */
function pagamentoDeGatewayDeTeste(): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

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

    return [$user, $plan, $subscription, $payment];
}

it('estorna no gateway e so depois muda o estado local', function () {
    $admin = User::factory()->admin()->create();
    [, , $subscription, $payment] = pagamentoDeGatewayDeTeste();

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_1/refunds' => Http::response([], 201),
        'api.mercadopago.com/preapproval/pre_123' => Http::response([
            'id' => 'pre_123',
            'status' => 'canceled',
        ]),
    ]);

    $this->actingAs($admin)
        ->from('/admin/payments')
        ->post(route('admin.payments.refund', $payment))
        ->assertRedirect('/admin/payments');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->renewal_canceled_at)->toBeNull()
        ->and(AdminAction::query()->sole()->action)->toBe(AdminActionType::PaymentRefunded);

    // O dinheiro sai antes de o banco dizer que saiu; a preapproval so para
    // depois do estorno, porque o estorno e o fato consumado.
    Http::assertSentInOrder([
        fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.mercadopago.com/v1/payments/pay_1/refunds',
        fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.mercadopago.com/preapproval/pre_123'
            && $request['status'] === 'canceled',
    ]);
});

it('nao mente quando o gateway recusa o estorno', function () {
    $admin = User::factory()->admin()->create();
    [, , $subscription, $payment] = pagamentoDeGatewayDeTeste();

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_1/refunds' => Http::response([], 500),
    ]);

    $this->actingAs($admin)
        ->from('/admin/payments')
        ->post(route('admin.payments.refund', $payment))
        ->assertRedirect('/admin/payments');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and(AdminAction::query()->count())->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.mercadopago.com/v1/payments/pay_1/refunds');

    $this->actingAs($admin)
        ->get('/admin/payments')
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'error')
            ->hasFlash('toast.message', __('admin.toasts.payment_refund_failed'))
        );
});

it('nao chama o gateway de novo para pagamento ja estornado', function () {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->for(User::factory())->refunded()->create();

    $this->actingAs($admin)
        ->from('/admin/payments')
        ->post(route('admin.payments.refund', $payment))
        ->assertRedirect('/admin/payments');

    Http::assertNothingSent();

    expect(AdminAction::query()->count())->toBe(0);
});

it('estorna o pagamento manual sem falar com o gateway', function () {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->for(User::factory())->create();

    $this->actingAs($admin)
        ->from('/admin/payments')
        ->post(route('admin.payments.refund', $payment))
        ->assertRedirect('/admin/payments');

    Http::assertNothingSent();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(AdminAction::query()->sole()->action)->toBe(AdminActionType::PaymentRefunded);
});
