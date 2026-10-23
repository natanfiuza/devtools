<?php

use App\Actions\Admin\GrantPlan;
use App\Actions\Admin\RefundPayment;
use App\Actions\Subscriptions\CancelActiveSubscriptions;
use App\Actions\Subscriptions\OpenSubscriptionPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLedger;
use App\Models\User;
use App\Support\Quota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('termina o mes sem overflow, inclusive em janeiro e em ano bissexto', function () {
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();

    Carbon::setTestNow('2026-01-31 10:00:00');

    expect($plano->periodEndFrom(now())?->toDateString())->toBe('2026-02-28');

    Carbon::setTestNow('2028-01-31 10:00:00');

    expect($plano->periodEndFrom(now())?->toDateString())->toBe('2028-02-29');
});

it('termina o ano sem overflow a partir de 29 de fevereiro', function () {
    $plano = Plan::factory()->yearly()->create();

    Carbon::setTestNow('2028-02-29 12:00:00');

    expect($plano->periodEndFrom(now())?->toDateString())->toBe('2029-02-28');
});

it('nao considera vigente o periodo que ainda nao comecou', function () {
    $plano = Plan::factory()->create();
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $corrente = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);

    $futura = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => $corrente->current_period_end,
        'current_period_end' => $corrente->current_period_end->copy()->addMonthNoOverflow(),
    ]);

    expect($futura->isCurrent())->toBeFalse()
        ->and($futura->isScheduled())->toBeTrue()
        ->and($corrente->isCurrent())->toBeTrue()
        ->and($corrente->isScheduled())->toBeFalse()
        ->and($user->activeSubscription()?->id)->toBe($corrente->id)
        ->and($user->nextSubscription()?->id)->toBe($futura->id)
        ->and(Subscription::query()->current()->pluck('id')->all())->toBe([$corrente->id]);
});

it('empilha a renovacao antecipada no fim do periodo pago', function () {
    $plano = Plan::factory()->create();
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $corrente = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);

    $nova = app(OpenSubscriptionPeriod::class)($user, $plano);

    expect($nova?->id)->not->toBe($corrente->id)
        ->and($nova?->current_period_start?->toDateTimeString())
        ->toBe($corrente->current_period_end?->toDateTimeString())
        ->and($nova?->current_period_end?->toDateString())
        ->toBe($corrente->current_period_end->copy()->addMonthNoOverflow()->toDateString())
        ->and($user->activeSubscription()?->id)->toBe($corrente->id)
        ->and($user->nextSubscription()?->id)->toBe($nova?->id)
        ->and(Subscription::query()->where('status', SubscriptionStatus::Active->value)->count())->toBe(2);
});

it('abre o periodo agora quando a renovacao chega depois do vencimento', function () {
    $plano = Plan::factory()->create();
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    Subscription::factory()->for($user)->expired()->create();

    $nova = app(OpenSubscriptionPeriod::class)($user, $plano);

    expect($nova?->current_period_start?->toDateString())->toBe('2026-03-01')
        ->and($user->activeSubscription()?->id)->toBe($nova?->id);
});

it('nao abre periodo para o Free', function () {
    $user = User::factory()->create();

    expect(app(OpenSubscriptionPeriod::class)($user, Plan::free()))->toBeNull()
        ->and(Subscription::query()->count())->toBe(0);
});

it('soma a cota apenas dos meses ja iniciados e acumula todo o consumo de tokens', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 1000]);
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $corrente = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);

    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => $corrente->current_period_end,
        'current_period_end' => $corrente->current_period_end->copy()->addMonthNoOverflow(),
    ]);

    UsageLedger::factory()->for($user)->create([
        'tokens' => 100,
        'created_at' => now()->subDay(),
    ]);

    $quota = Quota::for($user);

    // O periodo futuro ainda nao iniciou, entao nao credita antes da hora.
    expect($quota->limit())->toBe(1000)
        ->and($quota->used())->toBe(100)
        ->and($quota->remaining())->toBe(900)
        ->and($quota->windowEnd()?->toDateString())->toBe($corrente->current_period_end->toDateString());
});

it('concede o mesmo plano empilhando um periodo novo, sem esticar a linha corrente', function () {
    $plano = Plan::factory()->create();
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $corrente = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);

    $nova = app(GrantPlan::class)($user, $plano);

    expect($nova?->id)->not->toBe($corrente->id)
        ->and($nova?->gateway)->toBe('manual')
        ->and($nova?->current_period_start?->toDateTimeString())
        ->toBe($corrente->current_period_end?->toDateTimeString())
        ->and($corrente->refresh()->current_period_end?->toDateString())
        ->toBe(now()->addDays(20)->toDateString())
        ->and($corrente->status)->toBe(SubscriptionStatus::Active)
        ->and($user->activeSubscription()?->id)->toBe($corrente->id)
        ->and($user->nextSubscription()?->id)->toBe($nova?->id);
});

it('concede plano diferente encerrando o que valia e abrindo agora', function () {
    $atual = Plan::factory()->create(['name' => 'Mensal']);
    $novo = Plan::factory()->create(['name' => 'Anual', 'interval' => 'year']);
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $corrente = Subscription::factory()->for($user)->for($atual)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);

    $nova = app(GrantPlan::class)($user, $novo);

    expect($corrente->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($nova?->current_period_start?->toDateString())->toBe('2026-03-01')
        ->and($nova?->current_period_end?->toDateString())->toBe('2027-03-01')
        ->and($user->activeSubscription()?->plan->name)->toBe('Anual');
});

it('conceder o Free encerra a assinatura corrente e a futura', function () {
    $plano = Plan::factory()->create();
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $corrente = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);

    $futura = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => $corrente->current_period_end,
        'current_period_end' => $corrente->current_period_end->copy()->addMonthNoOverflow(),
    ]);

    expect(app(GrantPlan::class)($user, Plan::free()))->toBeNull()
        ->and($corrente->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($futura->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($user->activeSubscription())->toBeNull()
        ->and($user->nextSubscription())->toBeNull()
        ->and($user->effectivePlan()->slug)->toBe(Plan::FREE_SLUG);
});

it('estorna pagamento de periodo futuro sem derrubar o periodo corrente', function () {
    $plano = Plan::factory()->create();
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $corrente = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);

    $futura = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => $corrente->current_period_end,
        'current_period_end' => $corrente->current_period_end->copy()->addMonthNoOverflow(),
    ]);

    $pagamento = Payment::factory()->for($user)->create([
        'subscription_id' => $futura->id,
        'plan_id' => $plano->id,
    ]);

    app(RefundPayment::class)($pagamento);

    expect($pagamento->refresh()->status->value)->toBe('refunded')
        ->and($futura->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($corrente->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($user->activeSubscription()?->id)->toBe($corrente->id);
});

it('cancela todas as linhas que ainda valem sem tocar no historico vencido', function () {
    $plano = Plan::factory()->create();
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $vencida = Subscription::factory()->for($user)->for($plano)->expired()->create();
    $corrente = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);
    $futura = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->addDays(20),
        'current_period_end' => now()->addDays(50),
    ]);

    $canceladas = app(CancelActiveSubscriptions::class)($user);

    expect($canceladas)->toBe(2)
        ->and($vencida->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($corrente->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($futura->refresh()->status)->toBe(SubscriptionStatus::Canceled);
});

it('preserva a linha informada como excecao ao cancelar em volta', function () {
    $plano = Plan::factory()->create();
    $user = User::factory()->create();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $atual = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);
    $futura = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->addDays(20),
        'current_period_end' => now()->addDays(50),
    ]);

    app(CancelActiveSubscriptions::class)($user, $futura);

    expect($atual->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($futura->refresh()->status)->toBe(SubscriptionStatus::Active);
});
