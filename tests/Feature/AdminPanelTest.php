<?php

use App\Enums\NewsletterSource;
use App\Enums\PaymentStatus;
use App\Models\NewsletterSubscriber;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Painel administrativo (ADR-025): a porta e o que cada tela entrega.
|
| O Gate `access-admin` e as travas por conta (UserPolicy) tem teste proprio em
| AdminRoleTest. Aqui se prova o caminho HTTP: quem passa, o que a tela recebe
| e o que o filtro faz com a lista.
*/

/**
 * Inscrito na newsletter. `confirmed_at` e `unsubscribed_at` estao fora do
 * #[Fillable] de proposito (quem confirma e o clique no link), entao o estado
 * entra por forceFill - o mesmo caminho do fluxo real.
 *
 * @param  array<string, mixed>  $estado
 */
function assinante(string $email, array $estado = []): NewsletterSubscriber
{
    $subscriber = new NewsletterSubscriber([
        'email' => $email,
        'token' => NewsletterSubscriber::newToken(),
        'locale' => 'en',
        'source' => NewsletterSource::Footer->value,
    ]);

    $subscriber->forceFill($estado)->save();

    return $subscriber;
}

it('fecha o painel para visitante', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('fecha o painel para usuario comum', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('fecha o painel para admin de e-mail nao verificado', function () {
    $admin = User::factory()->admin()->unverified()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

it('derruba a sessao do admin suspenso', function () {
    $admin = User::factory()->admin()->create(['suspended_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('mostra a visao geral com os numeros do mes', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    User::factory()->create(['suspended_at' => now()]);

    Payment::factory()->for($user)->create([
        'amount_cents' => 2990,
        'paid_at' => now(),
    ]);

    // Pagamento estornado nao entra no que foi recebido.
    Payment::factory()->for($user)->refunded()->create(['paid_at' => now()]);

    // Assinante aqui e assinatura vigente, e nao inscrito na newsletter: sao
    // duas listas diferentes, e o numero da visao geral e o de quem paga.
    Subscription::factory()->for($user)->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('stats.accounts', 3)
            ->where('stats.subscribers', 1)
            ->where('stats.suspended', 1)
            ->where('stats.revenue.cents', 2990)
            ->has('recent', 3));
});

it('busca e filtra a lista de contas', function () {
    $admin = User::factory()->admin()->create(['name' => 'Dona do Painel']);
    User::factory()->create(['name' => 'Ana Souza', 'email' => 'ana@exemplo.com']);
    User::factory()->create(['name' => 'Bruno Lima', 'email' => 'bruno@exemplo.com']);
    User::factory()->create([
        'name' => 'Clara Dias',
        'email' => 'clara@exemplo.com',
        'suspended_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->has('users.data', 4)
            ->where('total', 4));

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => 'ana']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', 'ana@exemplo.com')
            ->where('filters.q', 'ana'));

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['role' => 'admin']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $admin->id));

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['status' => 'suspended']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1));

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['status' => 'unverified']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 0));

    // Papel fora do enum nao filtra nada - e nao deixa a lista vazia.
    $this->actingAs($admin)
        ->get(route('admin.users.index', ['role' => 'root']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 4));
});

it('mostra o plano vigente na lista de contas', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    Subscription::factory()->for($user)->for(Plan::query()->where('slug', 'pro-monthly')->sole())->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => $user->email]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.plan', 'Pro'));
});

it('abre o detalhe de uma conta com as travas da policy', function () {
    $admin = User::factory()->admin()->create();
    $outroAdmin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $suspenso = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/UserDetail')
            ->where('account.email', $user->email)
            ->where('can.manage', true)
            ->where('can.changeRole', true)
            ->where('can.impersonate', true)
            ->where('subscription', null)
            ->where('quota.unit', 'uses')
            ->where('quota.limit', 5)
            ->has('plans'));

    // A propria conta: nada de suspender, excluir ou entrar como.
    $this->actingAs($admin)
        ->get(route('admin.users.show', $admin))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manage', false)
            ->where('can.changeRole', false)
            ->where('can.impersonate', false));

    // Conta de outro admin: mesma trava.
    $this->actingAs($admin)
        ->get(route('admin.users.show', $outroAdmin))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manage', false)
            ->where('can.changeRole', true)
            ->where('can.impersonate', false));

    // Conta suspensa nao recebe visita.
    $this->actingAs($admin)
        ->get(route('admin.users.show', $suspenso))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manage', true)
            ->where('can.impersonate', false));
});

it('lista os assinantes e filtra por situacao', function () {
    $admin = User::factory()->admin()->create();

    assinante('confirmado@exemplo.com', ['confirmed_at' => now()]);
    assinante('pendente@exemplo.com');
    assinante('saiu@exemplo.com', [
        'confirmed_at' => now()->subMonth(),
        'unsubscribed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.subscribers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Subscribers')
            ->has('subscribers.data', 3));

    $this->actingAs($admin)
        ->get(route('admin.subscribers.index', ['status' => 'confirmed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.status', 'confirmed'));

    $this->actingAs($admin)
        ->get(route('admin.subscribers.index', ['status' => 'unsubscribed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.status', 'unsubscribed'));

    $this->actingAs($admin)
        ->get(route('admin.subscribers.index', ['q' => 'pendente']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.status', 'pending'));
});

it('exporta em csv a lista filtrada, e nao so a pagina', function () {
    $admin = User::factory()->admin()->create();

    assinante('confirmado@exemplo.com', ['confirmed_at' => now()]);
    assinante('pendente@exemplo.com');

    $response = $this->actingAs($admin)
        ->get(route('admin.subscribers.export', ['status' => 'confirmed']))
        ->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('email,locale,source,status,created_at')
        ->and($csv)->toContain('confirmado@exemplo.com')
        ->and($csv)->not->toContain('pendente@exemplo.com');
});

it('lista os planos com os numeros que o painel edita', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.plans.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Plans')
            ->has('plans', 3)
            ->where('plans.0.slug', 'free')
            ->where('plans.0.isFree', true)
            ->where('plans.0.priceCents', 0)
            ->where('plans.0.subscribers', 0)
            ->where('plans.1.slug', 'pro-monthly')
            ->where('plans.1.priceCents', 2990)
            ->where('plans.1.monthlyTokens', 2000000));
});

it('lista os pagamentos e filtra por situacao', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['email' => 'cliente@exemplo.com']);

    Payment::factory()->for($user)->create(['paid_at' => now()]);
    Payment::factory()->for($user)->refunded()->create(['paid_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Payments')
            ->has('payments.data', 2)
            ->has('options.plans', 3)
            ->has('options.methods', 4));

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['status' => 'refunded']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.status', PaymentStatus::Refunded->value));

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['q' => 'cliente@exemplo.com']))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 2));
});

it('mostra a auditoria e filtra por acao', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.suspend', $user));
    $this->actingAs($admin)->post(route('admin.users.unsuspend', $user));

    $this->actingAs($admin)
        ->get(route('admin.audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Audit')
            ->has('entries.data', 2)
            ->has('actionTypes', 16)
            ->where('entries.data.0.actor', $admin->email)
            ->where('entries.data.0.target', $user->name.' <'.$user->email.'>'));

    $this->actingAs($admin)
        ->get(route('admin.audit.index', ['action' => 'user.suspended']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'user.suspended'));
});
