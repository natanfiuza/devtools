<?php

use App\Enums\AdminActionType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AdminAction;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLedger;
use App\Models\User;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| As nove acoes do admin sobre uma conta (ADR-025).
|
| Cada uma tem duas provas: o efeito no dado e a linha de auditoria. Nao existe
| caminho que mude alguma coisa sem deixar rastro, nem rastro de coisa que nao
| mudou - e e isso que os testes abaixo verificam par a par.
*/

it('verifica o e-mail no lugar da pessoa', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->unverified()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.verify-email', $user))
        ->assertRedirect();

    expect($user->refresh()->email_verified_at)->not->toBeNull();

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::UserVerified)
        ->and($registro->actor_id)->toBe($admin->id)
        ->and($registro->target_id)->toBe($user->id)
        ->and($registro->target_type)->toBe(User::class)
        // Sem requisicao HTTP nao ha de onde tirar o IP (RecordAdminAction).
        ->and($registro->ip)->toBeNull();
});

it('nao registra auditoria quando o e-mail ja estava verificado', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $antes = $user->email_verified_at;

    $this->actingAs($admin)->post(route('admin.users.verify-email', $user));

    expect($user->refresh()->email_verified_at?->toDateTimeString())
        ->toBe($antes?->toDateTimeString())
        ->and(AdminAction::query()->count())->toBe(0);
});

it('reenvia a verificacao', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $user = User::factory()->unverified()->create();

    $this->actingAs($admin)->post(route('admin.users.resend-verification', $user));

    Notification::assertSentTo($user, VerifyEmail::class);

    expect(AdminAction::query()->sole()->action)
        ->toBe(AdminActionType::UserVerificationResent);
});

it('manda o link de redefinir senha', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.send-password-reset', $user));

    Notification::assertSentTo($user, ResetPassword::class);

    expect(AdminAction::query()->sole()->action)
        ->toBe(AdminActionType::UserPasswordResetSent);
});

it('promove e rebaixa, guardando de onde para onde', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.role', $user), ['role' => UserRole::Admin->value]);

    expect($user->refresh()->isAdmin())->toBeTrue();

    $promocao = AdminAction::query()->sole();

    expect($promocao->action)->toBe(AdminActionType::UserRoleGranted)
        ->and($promocao->meta)->toBe(['de' => 'user', 'para' => 'admin']);

    // Agora ele e admin: o rebaixamento continua permitido (e por aqui que se
    // deixa de ser admin), mas quem rebaixa nao pode ser o proprio.
    $this->actingAs($admin)
        ->post(route('admin.users.role', $user), ['role' => UserRole::User->value]);

    expect($user->refresh()->isAdmin())->toBeFalse();

    $rebaixamento = AdminAction::query()->orderByDesc('id')->first();

    expect($rebaixamento?->action)->toBe(AdminActionType::UserRoleRevoked)
        ->and($rebaixamento?->meta)->toBe(['de' => 'admin', 'para' => 'user']);
});

it('recusa mudar o proprio papel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.role', $admin), ['role' => UserRole::User->value])
        ->assertForbidden();

    expect($admin->refresh()->isAdmin())->toBeTrue()
        ->and(AdminAction::query()->count())->toBe(0);
});

it('recusa papel fora do enum', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.role', $user), ['role' => 'root'])
        ->assertSessionHasErrors('role');

    expect($user->refresh()->role)->toBe(UserRole::User)
        ->and(AdminAction::query()->count())->toBe(0);
});

it('suspende a conta e derruba a sessao aberta', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $this->actingAs($admin)->post(route('admin.users.suspend', $user));

    expect($user->refresh()->isSuspended())->toBeTrue()
        ->and(AdminAction::query()->sole()->action)->toBe(AdminActionType::UserSuspended);

    // Quem ja estava dentro cai no primeiro acesso seguinte.
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('recusa suspender outro admin e a propria conta', function () {
    $admin = User::factory()->admin()->create();
    $outroAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $outroAdmin))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $admin))
        ->assertForbidden();

    expect($admin->refresh()->isSuspended())->toBeFalse()
        ->and($outroAdmin->refresh()->isSuspended())->toBeFalse()
        ->and(AdminAction::query()->count())->toBe(0);
});

it('devolve o acesso sem tocar no que a conta tinha', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['suspended_at' => now()]);

    $assinatura = Subscription::factory()->for($user)->create();

    $this->actingAs($admin)->post(route('admin.users.unsuspend', $user));

    expect($user->refresh()->isSuspended())->toBeFalse()
        ->and($assinatura->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and(AdminAction::query()->sole()->action)->toBe(AdminActionType::UserUnsuspended);
});

it('concede um plano e abre a assinatura manual', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();

    $this->actingAs($admin)
        ->post(route('admin.users.plan', $user), ['plan' => $plano->id]);

    $assinatura = $user->refresh()->activeSubscription();

    expect($assinatura?->plan_id)->toBe($plano->id)
        ->and($assinatura?->gateway)->toBe('manual');

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::UserPlanGranted)
        ->and($registro->meta['plano'])->toBe('pro-monthly')
        ->and($registro->meta['ate'])->toBe($assinatura?->current_period_end?->toDateString());
});

it('conceder o Free encerra a assinatura vigente', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $assinatura = Subscription::factory()->for($user)->create();

    $free = Plan::free();

    $this->actingAs($admin)
        ->post(route('admin.users.plan', $user), ['plan' => $free->id]);

    expect($assinatura->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($user->refresh()->effectivePlan()->slug)->toBe(Plan::FREE_SLUG);
});

it('concede cota na unidade do plano efetivo', function () {
    $admin = User::factory()->admin()->create();
    $free = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.quota', $free), ['amount' => 10]);

    $lancamento = UsageLedger::query()->sole();

    // No Free a unidade e o uso, e nao o token.
    expect($lancamento->uses)->toBe(10)
        ->and($lancamento->tokens)->toBe(0);

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::UserQuotaGranted)
        ->and($registro->meta)->toBe(['quantidade' => 10, 'unidade' => 'uses']);

    // Assinante paga em tokens: mesma concessao, outra unidade.
    $assinante = User::factory()->create();

    Subscription::factory()->for($assinante)->for(
        Plan::query()->where('slug', 'pro-monthly')->sole(),
    )->create();

    $this->actingAs($admin)
        ->post(route('admin.users.quota', $assinante), ['amount' => 500]);

    $ultimo = UsageLedger::query()->orderByDesc('id')->first();

    expect($ultimo?->tokens)->toBe(500)
        ->and($ultimo?->uses)->toBe(0)
        ->and(AdminAction::query()->orderByDesc('id')->first()?->meta)
        ->toBe(['quantidade' => 500, 'unidade' => 'tokens']);
});

it('recusa cota que nao e quantidade', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.quota', $user), ['amount' => 0])
        ->assertSessionHasErrors('amount');

    expect(UsageLedger::query()->count())->toBe(0)
        ->and(AdminAction::query()->count())->toBe(0);
});

it('exclui a conta anonimizando, com o e-mail digitado', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['email' => 'sai@exemplo.com']);

    $pagamento = Payment::factory()->for($user)->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user), ['email' => 'sai@exemplo.com'])
        ->assertRedirect();

    $user->refresh();

    expect($user->anonymized_at)->not->toBeNull()
        ->and($user->name)->toBe('')
        ->and($user->email)->not->toBe('sai@exemplo.com')
        ->and($user->password)->toBeNull()
        ->and($user->role)->toBe(UserRole::User)
        ->and($user->locale)->toBeNull();

    // A cobranca fica: nota fiscal nao se apaga.
    expect($pagamento->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($pagamento->user_id)->toBe($user->id);

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::UserDeleted)
        ->and($registro->meta)->toBe(['email' => 'sai@exemplo.com']);
});

it('nao exclui nada quando o e-mail digitado nao confere', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['email' => 'sai@exemplo.com']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user), ['email' => 'outro@exemplo.com'])
        ->assertSessionHasErrors('email');

    expect($user->refresh()->anonymized_at)->toBeNull()
        ->and(AdminAction::query()->count())->toBe(0);
});

it('recusa excluir outro admin e a propria conta', function () {
    $admin = User::factory()->admin()->create();
    $outroAdmin = User::factory()->admin()->create(['email' => 'chefe@exemplo.com']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $outroAdmin), ['email' => 'chefe@exemplo.com'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin), ['email' => $admin->email])
        ->assertForbidden();

    expect($outroAdmin->refresh()->anonymized_at)->toBeNull()
        ->and($admin->refresh()->anonymized_at)->toBeNull()
        ->and(AdminAction::query()->count())->toBe(0);
});

it('recusa todas as acoes para quem nao e admin', function () {
    $user = User::factory()->create();
    $alvo = User::factory()->unverified()->create();

    $rotas = [
        ['post', 'admin.users.verify-email', []],
        ['post', 'admin.users.resend-verification', []],
        ['post', 'admin.users.send-password-reset', []],
        ['post', 'admin.users.role', ['role' => 'admin']],
        ['post', 'admin.users.suspend', []],
        ['post', 'admin.users.unsuspend', []],
        ['post', 'admin.users.plan', ['plan' => Plan::free()->id]],
        ['post', 'admin.users.quota', ['amount' => 5]],
        ['delete', 'admin.users.destroy', ['email' => $alvo->email]],
        ['post', 'admin.users.impersonate', []],
    ];

    foreach ($rotas as [$verbo, $nome, $dados]) {
        $this->actingAs($user)
            ->{$verbo}(route($nome, $alvo), $dados)
            ->assertForbidden();
    }

    expect($alvo->refresh()->email_verified_at)->toBeNull()
        ->and($alvo->isSuspended())->toBeFalse()
        ->and($alvo->anonymized_at)->toBeNull()
        ->and(AdminAction::query()->count())->toBe(0);
});

it('nao deixa a tela oferecer o que a policy nega', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.show', $admin))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manage', false)
            ->where('can.impersonate', false));
});
