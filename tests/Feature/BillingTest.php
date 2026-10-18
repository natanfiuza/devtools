<?php

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionIntentPaymentMethod;
use App\Enums\SubscriptionIntentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionIntent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('sobe com o catalogo de planos da Q11', function () {
    $planos = Plan::query()->orderBy('sort')->get();

    expect($planos)->toHaveCount(3);

    $free = Plan::free();

    expect($free->name)->toBe('Free')
        ->and($free->price_cents)->toBe(0)
        ->and($free->currency)->toBe('BRL')
        ->and($free->interval)->toBeNull()
        ->and($free->lifetime_free_uses)->toBe(5)
        ->and($free->max_schema_bytes)->toBe(8192)
        ->and($free->max_prompt_chars)->toBe(500)
        ->and($free->countsByUses())->toBeTrue();

    $mensal = Plan::query()->where('slug', 'pro-monthly')->sole();

    expect($mensal->price_cents)->toBe(890)
        ->and($mensal->interval)->toBe('month')
        ->and($mensal->monthly_tokens)->toBe(2000000)
        ->and($mensal->max_schema_bytes)->toBe(65536)
        ->and($mensal->max_prompt_chars)->toBe(4000)
        ->and($mensal->countsByUses())->toBeFalse();

    $anual = Plan::query()->where('slug', 'pro-yearly')->sole();

    expect($anual->price_cents)->toBe(8900)
        ->and($anual->interval)->toBe('year')
        ->and($anual->monthly_tokens)->toBe(2000000);
});

it('nao duplica nem sobrescreve o catalogo ao rodar a carga de novo', function () {
    Plan::query()->where('slug', 'pro-monthly')->sole()->update(['price_cents' => 1234]);

    // A migracao roda uma vez so; o que se prova aqui e que rodar de novo (um
    // deploy repetido, uma migracao reaplicada) nao estraga o catalogo.
    $seed = require database_path('migrations/2026_10_14_000004_seed_plans_catalog.php');
    $seed->up();
    $seed->up();

    expect(Plan::query()->count())->toBe(3)
        ->and(Plan::query()->where('slug', 'pro-monthly')->sole()->price_cents)->toBe(1234);
});

it('da o Free a quem nao tem assinatura', function () {
    $user = User::factory()->create();

    expect($user->effectivePlan()->slug)->toBe(Plan::FREE_SLUG)
        ->and($user->activeSubscription())->toBeNull();
});

it('da o plano da assinatura vigente', function () {
    $plano = Plan::factory()->create(['name' => 'Time']);
    $user = User::factory()->create();

    Subscription::factory()->for($user)->for($plano)->create();

    expect($user->effectivePlan()->name)->toBe('Time');
});

it('volta ao Free quando a assinatura venceu', function () {
    $user = User::factory()->create();

    Subscription::factory()->for($user)->expired()->create();

    expect($user->activeSubscription())->toBeNull()
        ->and($user->effectivePlan()->slug)->toBe(Plan::FREE_SLUG);
});

it('volta ao Free quando a assinatura foi cancelada', function () {
    $user = User::factory()->create();

    Subscription::factory()->for($user)->canceled()->create();

    expect($user->effectivePlan()->slug)->toBe(Plan::FREE_SLUG);
});

it('trata a vigencia pela data, sem rotina agendada', function () {
    $user = User::factory()->create();

    $vigente = Subscription::factory()->for($user)->create();
    $vencida = Subscription::factory()->for($user)->expired()->create();
    $cancelada = Subscription::factory()->for($user)->canceled()->create();
    $semFim = Subscription::factory()->for($user)->lifetime()->create();

    expect($vigente->isCurrent())->toBeTrue()
        ->and($vencida->isCurrent())->toBeFalse()
        ->and($cancelada->isCurrent())->toBeFalse()
        ->and($semFim->isCurrent())->toBeTrue();

    expect(Subscription::query()->current()->pluck('id')->all())
        ->toEqualCanonicalizing([$vigente->id, $semFim->id]);
});

it('escolhe a assinatura mais recente quando ha mais de uma vigente', function () {
    $user = User::factory()->create();

    $antiga = Plan::factory()->create(['name' => 'Antiga']);
    $nova = Plan::factory()->create(['name' => 'Nova']);

    Subscription::factory()->for($user)->for($antiga)
        ->create(['current_period_start' => now()->subMonth()]);

    Subscription::factory()->for($user)->for($nova)
        ->create(['current_period_start' => now()->subDay()]);

    expect($user->activeSubscription()?->plan->name)->toBe('Nova')
        ->and($user->effectivePlan()->name)->toBe('Nova');
});

it('liga planos, assinaturas e pagamentos a conta', function () {
    $user = User::factory()->create();
    $plano = Plan::factory()->create();
    $assinatura = Subscription::factory()->for($user)->for($plano)->create();

    $pagamento = Payment::factory()->for($user)->create([
        'subscription_id' => $assinatura->id,
        'plan_id' => $plano->id,
    ]);

    expect($user->subscriptions()->count())->toBe(1)
        ->and($user->payments()->count())->toBe(1)
        ->and($pagamento->user->is($user))->toBeTrue()
        ->and($pagamento->plan?->is($plano))->toBeTrue()
        ->and($pagamento->subscription?->is($assinatura))->toBeTrue()
        ->and($plano->subscriptions()->count())->toBe(1)
        ->and($plano->payments()->count())->toBe(1)
        ->and($assinatura->payments()->count())->toBe(1);
});

it('marca a cobranca estornada sem apagar o registro', function () {
    $pagamento = Payment::factory()->refunded()->create();

    expect($pagamento->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($pagamento->amount_cents)->toBe(2990);
});

it('mantem so assinaturas ativas no escopo de vigentes', function () {
    Subscription::factory()->create();
    Subscription::factory()->canceled()->create();
    Subscription::factory()->expired()->create();

    $vigentes = Subscription::query()->current()->get();

    expect($vigentes)->toHaveCount(1)
        ->and($vigentes->first()?->status)->toBe(SubscriptionStatus::Active);
});

it('entrega as faturas sem intencao pendente quando a conta nao possui pedido', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard/billing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Billing')
            ->where('pendingIntent', null)
        );
});

it('entrega a intencao de assinatura em analise na aba faturas', function () {
    $user = User::factory()->create();
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    SubscriptionIntent::query()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'username' => 'natan_analise',
        'phone' => '+55 11 99999-0000',
        'document' => null,
        'payment_method' => SubscriptionIntentPaymentMethod::Pix,
        'status' => SubscriptionIntentStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get('/dashboard/billing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Billing')
            ->where('pendingIntent.planSlug', 'pro-monthly')
            ->where('pendingIntent.status', 'pending')
            ->has('pendingIntent.createdAt')
        );
});
