<?php

use App\Enums\CheckoutMode;
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
    ]);

    Http::preventStrayRequests();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function assinaturaRecorrenteDeTeste(
    User $user,
    Plan $plan,
    array $attributes = [],
): Subscription {
    return Subscription::factory()->for($user)->for($plan)->create(array_merge([
        'gateway' => 'mercadopago',
        'gateway_ref' => 'pre_123',
        'status' => SubscriptionStatus::Active,
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ], $attributes));
}

it('oferece o cancelamento apenas para renovacao de gateway viva', function () {
    $comGateway = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    assinaturaRecorrenteDeTeste($comGateway, $plano);

    $this->actingAs($comGateway)
        ->get('/dashboard/billing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Billing')
            ->where('subscription.renewable', true)
            ->where('subscription.renewalCanceledAt', null)
            ->where('subscription.renewalFailedAt', null)
        );

    $manual = User::factory()->create();
    Subscription::factory()->for($manual)->for($plano)->create();

    $this->actingAs($manual)
        ->get('/dashboard/billing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscription.renewable', false)
        );
});

it('cancela no gateway e mantem o acesso ate o fim do periodo', function () {
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    $assinatura = assinaturaRecorrenteDeTeste($user, $plano);

    Http::fake([
        'api.mercadopago.com/preapproval/pre_123' => Http::response([
            'id' => 'pre_123',
            'status' => 'canceled',
        ]),
    ]);

    $this->actingAs($user)
        ->from('/dashboard/billing')
        ->post('/dashboard/billing/cancel-recurring')
        ->assertRedirect('/dashboard/billing');

    $assinatura->refresh();

    expect($assinatura->renewal_canceled_at)->not->toBeNull()
        ->and($assinatura->status)->toBe(SubscriptionStatus::Active);

    $user->refresh();

    // O periodo pago continua valendo: o que para e a cobranca seguinte.
    expect($user->activeSubscription()?->id)->toBe($assinatura->id)
        ->and($user->effectivePlan()->id)->toBe($plano->id);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.mercadopago.com/preapproval/pre_123'
        && $request['status'] === 'canceled');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->hasFlash('toast.type', 'success'));
});

it('nao muda nada quando o gateway recusa o cancelamento', function () {
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    $assinatura = assinaturaRecorrenteDeTeste($user, $plano);

    Http::fake([
        'api.mercadopago.com/preapproval/pre_123' => Http::response([], 500),
    ]);

    $this->actingAs($user)
        ->from('/dashboard/billing')
        ->post('/dashboard/billing/cancel-recurring')
        ->assertRedirect('/dashboard/billing');

    expect($assinatura->refresh()->renewal_canceled_at)->toBeNull()
        ->and($assinatura->status)->toBe(SubscriptionStatus::Active)
        ->and($user->refresh()->activeSubscription()?->id)->toBe($assinatura->id);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'error')
            ->hasFlash('toast.message', __('account.billing.renewal.cancel_failed'))
        );
});

it('trata preapproval ja ausente como cancelada', function () {
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    $assinatura = assinaturaRecorrenteDeTeste($user, $plano);

    Http::fake([
        'api.mercadopago.com/preapproval/pre_123' => Http::response([], 404),
    ]);

    $this->actingAs($user)
        ->from('/dashboard/billing')
        ->post('/dashboard/billing/cancel-recurring')
        ->assertRedirect('/dashboard/billing');

    expect($assinatura->refresh()->renewal_canceled_at)->not->toBeNull();
});

it('nao chama o gateway quando nao ha renovacao', function () {
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    Subscription::factory()->for($user)->for($plano)->create();

    $this->actingAs($user)
        ->from('/dashboard/billing')
        ->post('/dashboard/billing/cancel-recurring')
        ->assertRedirect('/dashboard/billing');

    Http::assertNothingSent();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'info')
            ->hasFlash('toast.message', __('account.billing.renewal.nothing_to_cancel'))
        );
});

it('mostra o proximo periodo empilhado', function () {
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    $atual = assinaturaRecorrenteDeTeste($user, $plano);

    assinaturaRecorrenteDeTeste($user, $plano, [
        'gateway_ref' => null,
        'current_period_start' => $atual->current_period_end,
        'current_period_end' => $atual->current_period_end?->copy()->addMonthNoOverflow(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard/billing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nextSubscription.plan', $plano->name)
            ->has('nextSubscription.start')
            ->has('nextSubscription.end')
        );
});

it('mostra o checkout pendente e o processamento da recorrencia', function () {
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();

    CheckoutSession::factory()->for($user)->for($plano)->recurring()->create([
        'status' => CheckoutStatus::Pending,
        'authorized_at' => now(),
        'expires_at' => null,
    ]);

    $this->actingAs($user)
        ->get('/dashboard/billing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lastCheckout.processing', true)
            ->where('lastCheckout.status', 'pending')
            ->where('lastCheckout.mode', CheckoutMode::Recurring->value)
            ->where('lastCheckout.planName', $plano->name)
        );
});

it('nao mostra checkout pendente fora do prazo', function () {
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();

    CheckoutSession::factory()->for($user)->for($plano)->create([
        'status' => CheckoutStatus::Pending,
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard/billing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('lastCheckout', null));
});
