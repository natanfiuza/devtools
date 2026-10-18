<?php

use App\Enums\AdminActionType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AdminAction;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionIntent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('fecha o hub de assinaturas para visitante', function () {
    $this->get(route('admin.subscriptions.index'))->assertRedirect(route('login'));
});

it('fecha o hub de assinaturas para usuario comum', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.subscriptions.index'))
        ->assertForbidden();
});

it('abre o hub de assinaturas para admin com metricas e assinaturas vigentes', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'gateway' => 'manual',
        'status' => SubscriptionStatus::Active,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Subscriptions')
            ->where('tab', 'active')
            ->has('stats')
            ->where('stats.active', 1)
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.userId', $user->id)
        );
});

it('lista intencoes de assinatura na aba intents', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    SubscriptionIntent::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'username' => 'usuario1',
        'phone' => '+5511999999999',
        'document' => '12345678909',
        'payment_method' => 'pix',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.subscriptions.index', ['tab' => 'intents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Subscriptions')
            ->where('tab', 'intents')
            ->has('intents.data', 1)
            ->where('intents.data.0.username', 'usuario1')
        );
});

it('lista pagamentos na aba payments', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    Payment::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'amount_cents' => 2990,
        'currency' => 'BRL',
        'method' => PaymentMethod::Pix,
        'status' => PaymentStatus::Paid,
        'gateway' => 'manual',
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.subscriptions.index', ['tab' => 'payments']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Subscriptions')
            ->where('tab', 'payments')
            ->has('payments.data', 1)
            ->where('payments.data.0.amount', fn ($amount) => str_contains((string) $amount, '29'))
        );
});

it('lista contas excluidas e em carencia na aba deleted', function () {
    $admin = User::factory()->admin()->create();
    $trashedUser = User::factory()->create();
    $trashedUser->delete();

    $this->actingAs($admin)
        ->get(route('admin.subscriptions.index', ['tab' => 'deleted']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Subscriptions')
            ->where('tab', 'deleted')
            ->has('deletedAccounts.data', 1)
            ->where('deletedAccounts.data.0.id', $trashedUser->id)
            ->where('deletedAccounts.data.0.canRestore', true)
        );
});

it('permite restaurar conta em soft delete e registra auditoria', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $user->delete();

    expect($user->trashed())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.users.restore', $user))
        ->assertRedirect();

    expect($user->refresh()->trashed())->toBeFalse();

    $action = AdminAction::query()->latest('id')->first();
    expect($action)->not->toBeNull()
        ->and($action->action)->toBe(AdminActionType::UserRestored)
        ->and($action->target_id)->toBe($user->id)
        ->and($action->actor_id)->toBe($admin->id);
});

it('impede restaurar conta anonimizada', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $user->forceFill(['anonymized_at' => now()])->save();
    $user->delete();

    $this->actingAs($admin)
        ->post(route('admin.users.restore', $user))
        ->assertForbidden();
});
