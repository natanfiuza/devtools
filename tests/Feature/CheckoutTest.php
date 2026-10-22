<?php

use App\Enums\CheckoutStatus;
use App\Enums\SubscriptionStatus;
use App\Models\CheckoutSession;
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
        'payments.checkout.back_url' => null,
        'payments.checkout.webhook_url' => null,
    ]);
});

/**
 * Assinatura com renovacao automatica viva no provedor.
 */
function recurringSubscription(User $user, Plan $plan, string $reference = 'pre_123'): Subscription
{
    return Subscription::factory()->for($user)->for($plan)->create([
        'gateway' => 'mercadopago',
        'gateway_ref' => $reference,
        'status' => SubscriptionStatus::Active,
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);
}

it('cria a preferencia e manda o navegador ao init_point', function () {
    Http::fake([
        'api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'pref_123',
            'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_123',
        ], 201),
    ]);

    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price_cents' => 890, 'currency' => 'BRL']);

    $response = $this->actingAs($user)->post('/subscribe/checkout', [
        'plan' => $plan->slug,
        'mode' => 'one_time',
    ]);

    $session = CheckoutSession::query()->sole();

    $response->assertRedirect('https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_123');

    expect($session->user_id)->toBe($user->id)
        ->and($session->plan_id)->toBe($plan->id)
        ->and($session->mode->value)->toBe('one_time')
        ->and($session->status)->toBe(CheckoutStatus::Pending)
        ->and($session->amount_cents)->toBe(890)
        ->and($session->currency)->toBe('BRL')
        ->and($session->gateway)->toBe('mercadopago')
        ->and($session->gateway_ref)->toBe('pref_123')
        ->and($session->init_point)->toContain('pref_123')
        ->and($session->expires_at?->isFuture())->toBeTrue();

    Http::assertSent(function (Request $request) use ($session): bool {
        $body = $request->data();

        return $request->url() === 'https://api.mercadopago.com/checkout/preferences'
            && $request->hasHeader('X-Idempotency-Key', 'chk_'.$session->ulid)
            && $body['external_reference'] === 'chk_'.$session->ulid
            && $body['back_urls']['success'] === route('subscribe.checkout.return', ['checkoutSession' => $session->ulid]);
    });
});

it('cria a preapproval mensal ou anual sem liberar periodo', function (string $interval, int $frequency) {
    Http::fake([
        'api.mercadopago.com/preapproval' => Http::response([
            'id' => 'pre_123',
            'status' => 'pending',
            'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=pre_123',
            'auto_recurring' => ['transaction_amount' => 8.9, 'currency_id' => 'BRL'],
        ], 201),
    ]);

    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'interval' => $interval,
        'price_cents' => $interval === 'year' ? 8900 : 890,
    ]);

    $response = $this->actingAs($user)->post('/subscribe/checkout', [
        'plan' => $plan->slug,
        'mode' => 'recurring',
    ]);

    $session = CheckoutSession::query()->sole();

    $response->assertRedirect('https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=pre_123');

    expect($session->mode->value)->toBe('recurring')
        ->and($session->gateway_ref)->toBe('pre_123')
        ->and($session->approved_at)->toBeNull()
        ->and($user->subscriptions()->count())->toBe(0);

    Http::assertSent(function (Request $request) use ($frequency): bool {
        $body = $request->data();

        return $request->url() === 'https://api.mercadopago.com/preapproval'
            && $body['auto_recurring']['frequency'] === $frequency
            && $body['auto_recurring']['frequency_type'] === 'months'
            && ! isset($body['preapproval_plan_id']);
    });
})->with([
    'mensal' => ['month', 1],
    'anual' => ['year', 12],
]);

it('cancela a preapproval antes de abrir pagamento avulso no mesmo plano', function () {
    Http::fake([
        'api.mercadopago.com/preapproval/pre_123' => Http::response(['id' => 'pre_123', 'status' => 'canceled']),
        'api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'pref_123',
            'init_point' => 'https://www.mercadopago.com.br/checkout?pref_id=pref_123',
        ], 201),
    ]);

    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    $subscription = recurringSubscription($user, $plan);

    $this->actingAs($user)->post('/subscribe/checkout', [
        'plan' => $plan->slug,
        'mode' => 'one_time',
    ])->assertRedirect('https://www.mercadopago.com.br/checkout?pref_id=pref_123');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->renewal_canceled_at)->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.mercadopago.com/preapproval/pre_123');
});

it('cancela a preapproval do plano antigo ao trocar de plano', function () {
    Http::fake([
        'api.mercadopago.com/preapproval/pre_123' => Http::response(['id' => 'pre_123', 'status' => 'canceled']),
        'api.mercadopago.com/preapproval' => Http::response([
            'id' => 'pre_456',
            'status' => 'pending',
            'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=pre_456',
        ], 201),
    ]);

    $user = User::factory()->create();
    $oldPlan = Plan::factory()->create();
    $newPlan = Plan::factory()->yearly()->create();

    $subscription = recurringSubscription($user, $oldPlan);

    $this->actingAs($user)->post('/subscribe/checkout', [
        'plan' => $newPlan->slug,
        'mode' => 'recurring',
    ])->assertRedirect('https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=pre_456');

    expect($subscription->refresh()->renewal_canceled_at)->not->toBeNull()
        ->and(CheckoutSession::query()->sole()->gateway_ref)->toBe('pre_456');
});

it('bloqueia uma segunda renovacao no mesmo plano', function () {
    Http::fake();

    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    recurringSubscription($user, $plan);

    $this->from('/subscribe')
        ->actingAs($user)
        ->post('/subscribe/checkout', [
            'plan' => $plan->slug,
            'mode' => 'recurring',
        ])
        ->assertRedirect('/subscribe');

    expect(CheckoutSession::query()->count())->toBe(0);

    Http::assertNothingSent();
});

it('marca a sessao como falha quando o provedor recusa a criacao', function () {
    Http::fake([
        'api.mercadopago.com/checkout/preferences' => Http::response(['message' => 'erro interno'], 500),
    ]);

    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $this->from('/subscribe')
        ->actingAs($user)
        ->post('/subscribe/checkout', [
            'plan' => $plan->slug,
            'mode' => 'one_time',
        ])
        ->assertRedirect('/subscribe');

    expect(CheckoutSession::query()->sole()->status)->toBe(CheckoutStatus::Failed);
});

it('recusa o checkout online sem credenciais do provedor', function () {
    config(['payments.mercadopago.access_token' => null]);
    Http::fake();

    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $this->from('/subscribe')
        ->actingAs($user)
        ->post('/subscribe/checkout', [
            'plan' => $plan->slug,
            'mode' => 'one_time',
        ])
        ->assertRedirect('/subscribe');

    expect(CheckoutSession::query()->count())->toBe(0);

    Http::assertNothingSent();
});

it('nao abre checkout de plano gratuito', function () {
    Http::fake();

    $user = User::factory()->create();
    $free = Plan::free();

    $this->from('/subscribe')
        ->actingAs($user)
        ->post('/subscribe/checkout', [
            'plan' => $free->slug,
            'mode' => 'one_time',
        ])
        ->assertRedirect('/subscribe');

    expect(CheckoutSession::query()->count())->toBe(0);
});

it('devolve a sessao para a pagina de assinatura somente para o dono', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $plan = Plan::factory()->create();

    $session = CheckoutSession::factory()->for($owner)->for($plan)->create();

    $this->actingAs($other)
        ->get(route('subscribe.checkout.return', ['checkoutSession' => $session->ulid]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->get(route('subscribe.checkout.return', ['checkoutSession' => $session->ulid]))
        ->assertRedirect(route('subscribe', [
            'plan' => $plan->slug,
            'checkout' => $session->ulid,
        ]));
});

it('mostra na pagina de assinatura o estado efetivo do checkout', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $pending = CheckoutSession::factory()->for($user)->for($plan)->create([
        'expires_at' => now()->addMinutes(30),
    ]);

    $stale = CheckoutSession::factory()->for($user)->for($plan)->create([
        'expires_at' => now()->subMinute(),
    ]);

    $authorized = CheckoutSession::factory()->recurring()->for($user)->for($plan)->create([
        'expires_at' => now()->subMinute(),
        'authorized_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->get('/subscribe?checkout='.$pending->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subscribe')
            ->where('onlineCheckoutAvailable', true)
            ->where('checkout.status', 'pending')
            ->where('checkout.planSlug', $plan->slug)
        );

    $this->actingAs($user)
        ->get('/subscribe?checkout='.$stale->ulid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkout.status', 'expired')
        );

    $this->actingAs($user)
        ->get('/subscribe?checkout='.$authorized->ulid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkout.status', 'pending')
            ->where('checkout.mode', 'recurring')
        );
});

it('lista os planos com renovacao ativa e esconde o checkout sem credenciais', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    recurringSubscription($user, $plan);

    $this->actingAs($user)
        ->get('/subscribe')
        ->assertInertia(fn (Assert $page) => $page
            ->where('onlineCheckoutAvailable', true)
            ->where('recurringPlanSlugs', [$plan->slug])
        );

    config(['payments.mercadopago.access_token' => null]);

    $this->actingAs($user)
        ->get('/subscribe')
        ->assertInertia(fn (Assert $page) => $page
            ->where('onlineCheckoutAvailable', false)
        );
});
