<?php

use App\Enums\AdminActionType;
use App\Enums\NewsletterSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AdminAction;
use App\Models\NewsletterSubscriber;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Catalogo, cobranca manual e lista de assinantes (ADR-027 e ADR-024).
|
| O preco e dado do admin: editar aqui e o jeito previsto de mudar preco, e o
| registro manual e a unica porta por onde entra dinheiro sem gateway.
*/

it('edita os numeros de um plano e registra a auditoria', function () {
    $admin = User::factory()->admin()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();

    $this->actingAs($admin)
        ->patch(route('admin.plans.update', $plano), [
            'name' => 'Pro mensal',
            'price_cents' => 3490,
            'interval' => 'month',
            'monthly_tokens' => 3000000,
            'max_schema_bytes' => 65536,
            'max_prompt_chars' => 4000,
            'lifetime_free_uses' => 0,
            'active' => true,
            'sort' => 1,
        ])
        ->assertRedirect(route('admin.plans.index'));

    $plano->refresh();

    expect($plano->name)->toBe('Pro mensal')
        ->and($plano->price_cents)->toBe(3490)
        ->and($plano->monthly_tokens)->toBe(3000000);

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::PlanUpdated)
        ->and($registro->target_id)->toBe($plano->id)
        ->and($registro->meta['campos'])->toEqualCanonicalizing([
            'name', 'price_cents', 'monthly_tokens',
        ]);
});

it('nao registra auditoria quando nada mudou', function () {
    $admin = User::factory()->admin()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();

    $this->actingAs($admin)
        ->patch(route('admin.plans.update', $plano), [
            'name' => $plano->name,
            'price_cents' => $plano->price_cents,
            'interval' => $plano->interval,
            'monthly_tokens' => $plano->monthly_tokens,
            'max_schema_bytes' => $plano->max_schema_bytes,
            'max_prompt_chars' => $plano->max_prompt_chars,
            'lifetime_free_uses' => $plano->lifetime_free_uses,
            'active' => $plano->active,
            'sort' => $plano->sort,
        ]);

    expect(AdminAction::query()->count())->toBe(0);
});

it('nao deixa o slug do plano mudar', function () {
    $admin = User::factory()->admin()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();

    $this->actingAs($admin)
        ->patch(route('admin.plans.update', $plano), [
            'slug' => 'outro-slug',
            'name' => $plano->name,
            'price_cents' => 3990,
            'interval' => 'month',
            'monthly_tokens' => $plano->monthly_tokens,
            'max_schema_bytes' => $plano->max_schema_bytes,
            'max_prompt_chars' => $plano->max_prompt_chars,
            'lifetime_free_uses' => 0,
            'active' => true,
            'sort' => 1,
        ]);

    // O slug e a identidade do plano: o codigo procura o Free por ele.
    expect($plano->refresh()->slug)->toBe('pro-monthly')
        ->and($plano->price_cents)->toBe(3990);
});

it('recusa plano com preco negativo', function () {
    $admin = User::factory()->admin()->create();
    $plano = Plan::query()->where('slug', 'free')->sole();

    $this->actingAs($admin)
        ->patch(route('admin.plans.update', $plano), ['price_cents' => -1])
        ->assertSessionHasErrors('price_cents');

    expect($plano->refresh()->price_cents)->toBe(0)
        ->and(AdminAction::query()->count())->toBe(0);
});

it('registra um pagamento recebido por fora, com o valor em moeda', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['email' => 'cliente@exemplo.com']);
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();

    $this->actingAs($admin)
        ->post(route('admin.payments.store'), [
            'email' => 'cliente@exemplo.com',
            'plan' => $plano->id,
            // Como a pessoa escreve na moeda dela: a virgula e o separador
            // decimal em quatro dos cinco idiomas da tela.
            'amount' => '1.234,56',
            'method' => PaymentMethod::Pix->value,
            'note' => 'Pix do mes',
        ])
        ->assertRedirect();

    $pagamento = Payment::query()->sole();

    expect($pagamento->amount_cents)->toBe(123456)
        ->and($pagamento->currency)->toBe('BRL')
        ->and($pagamento->method)->toBe(PaymentMethod::Pix)
        ->and($pagamento->status)->toBe(PaymentStatus::Paid)
        ->and($pagamento->gateway)->toBe('manual')
        ->and($pagamento->plan_id)->toBe($plano->id)
        ->and($pagamento->note)->toBe('Pix do mes');

    // Sem `grant_plan` o pagamento nao da acesso: cobranca e acesso sao duas
    // coisas.
    expect($user->refresh()->activeSubscription())->toBeNull();

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::PaymentRegistered)
        ->and($registro->meta['conta'])->toBe('cliente@exemplo.com')
        ->and($registro->meta['valor'])->toBe(123456);
});

it('aceita o valor em ponto decimal', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'cliente@exemplo.com']);

    $this->actingAs($admin)
        ->post(route('admin.payments.store'), [
            'email' => 'cliente@exemplo.com',
            'amount' => '29.90',
            'method' => PaymentMethod::Transfer->value,
        ]);

    expect(Payment::query()->sole()->amount_cents)->toBe(2990);
});

it('recusa valor que nao da para ler', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'cliente@exemplo.com']);

    $this->actingAs($admin)
        ->post(route('admin.payments.store'), [
            'email' => 'cliente@exemplo.com',
            'amount' => 'dois reais',
            'method' => PaymentMethod::Cash->value,
        ])
        ->assertSessionHasErrors('amount');

    expect(Payment::query()->count())->toBe(0)
        ->and(AdminAction::query()->count())->toBe(0);
});

it('recusa pagamento de conta que nao existe', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.payments.store'), [
            'email' => 'ninguem@exemplo.com',
            'amount' => '29,90',
            'method' => PaymentMethod::Pix->value,
        ])
        ->assertSessionHasErrors('email');
});

it('concede o plano junto quando o registro pede', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-yearly')->sole();

    $this->actingAs($admin)
        ->post(route('admin.payments.store'), [
            'email' => $user->email,
            'plan' => $plano->id,
            'amount' => '299,00',
            'method' => PaymentMethod::Pix->value,
            'grant_plan' => true,
        ]);

    $assinatura = $user->refresh()->activeSubscription();

    expect($assinatura?->plan_id)->toBe($plano->id)
        ->and(Payment::query()->sole()->subscription_id)->toBe($assinatura?->id);
});

it('estorna o pagamento e encerra a assinatura que ele abriu', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();

    $this->actingAs($admin)->post(route('admin.payments.store'), [
        'email' => $user->email,
        'plan' => $plano->id,
        'amount' => '29,90',
        'method' => PaymentMethod::Pix->value,
        'grant_plan' => true,
    ]);

    $pagamento = Payment::query()->sole();
    $assinatura = $user->refresh()->activeSubscription();

    $this->actingAs($admin)
        ->post(route('admin.payments.refund', $pagamento))
        ->assertRedirect();

    expect($pagamento->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($assinatura?->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($user->refresh()->activeSubscription())->toBeNull();

    // A linha do pagamento fica: estorno e estado, e nao exclusao.
    expect(Payment::query()->count())->toBe(1)
        ->and(AdminAction::query()->orderByDesc('id')->first()?->action)
        ->toBe(AdminActionType::PaymentRefunded);
});

it('nao estorna duas vezes', function () {
    $admin = User::factory()->admin()->create();
    $pagamento = Payment::factory()->refunded()->create();

    $this->actingAs($admin)->post(route('admin.payments.refund', $pagamento));

    expect($pagamento->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(AdminAction::query()->count())->toBe(0);
});

it('reinscreve e descadastra sem apagar a linha', function () {
    $admin = User::factory()->admin()->create();

    $subscriber = new NewsletterSubscriber([
        'email' => 'leitor@exemplo.com',
        'token' => NewsletterSubscriber::newToken(),
        'locale' => 'pt_BR',
        'source' => NewsletterSource::Footer->value,
    ]);

    $subscriber->forceFill(['confirmed_at' => now(), 'unsubscribed_at' => now()])->save();

    $this->actingAs($admin)
        ->post(route('admin.subscribers.resubscribe', $subscriber))
        ->assertRedirect();

    expect($subscriber->refresh()->unsubscribed_at)->toBeNull()
        ->and(AdminAction::query()->sole()->action)
        ->toBe(AdminActionType::SubscriberResubscribed);

    $this->actingAs($admin)
        ->delete(route('admin.subscribers.destroy', $subscriber))
        ->assertRedirect();

    // Descadastrar nao apaga: apagar deixaria a pessoa ser reinscrita amanha
    // pelo formulario, sem nunca ter pedido (ADR-024).
    expect(NewsletterSubscriber::query()->count())->toBe(1)
        ->and($subscriber->refresh()->unsubscribed_at)->not->toBeNull()
        ->and(AdminAction::query()->orderByDesc('id')->first()?->action)
        ->toBe(AdminActionType::SubscriberRemoved);
});

it('nao confirma ninguem por tabela ao reinscrever', function () {
    $admin = User::factory()->admin()->create();

    // Pendente que nunca clicou no link.
    $subscriber = new NewsletterSubscriber([
        'email' => 'leitor@exemplo.com',
        'token' => NewsletterSubscriber::newToken(),
        'locale' => 'pt_BR',
        'source' => NewsletterSource::Footer->value,
    ]);

    $subscriber->forceFill(['unsubscribed_at' => now()])->save();

    $this->actingAs($admin)->post(route('admin.subscribers.resubscribe', $subscriber));

    expect($subscriber->refresh()->confirmed_at)->toBeNull()
        ->and($subscriber->unsubscribed_at)->toBeNull();
});

it('recusa as acoes de cobranca para quem nao e admin', function () {
    $user = User::factory()->create();
    $plano = Plan::query()->where('slug', 'free')->sole();
    $pagamento = Payment::factory()->create();

    $subscriber = new NewsletterSubscriber([
        'email' => 'leitor@exemplo.com',
        'token' => NewsletterSubscriber::newToken(),
        'locale' => 'pt_BR',
        'source' => NewsletterSource::Footer->value,
    ]);

    $subscriber->save();

    $this->actingAs($user)
        ->patch(route('admin.plans.update', $plano), ['name' => 'X'])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.payments.store'), ['email' => $user->email, 'amount' => '1', 'method' => 'pix'])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.payments.refund', $pagamento))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('admin.subscribers.destroy', $subscriber))
        ->assertForbidden();

    expect($plano->refresh()->name)->toBe('Free')
        ->and($pagamento->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(Payment::query()->count())->toBe(1)
        ->and($subscriber->refresh()->unsubscribed_at)->toBeNull()
        ->and(AdminAction::query()->count())->toBe(0);
});

it('nao deixa o catalogo sem plano gratuito', function () {
    $admin = User::factory()->admin()->create();
    $free = Plan::free();

    // Tirar o Free de venda nao apaga o plano: quem esta nele continua nele.
    $this->actingAs($admin)->patch(route('admin.plans.update', $free), [
        'name' => $free->name,
        'price_cents' => 0,
        'interval' => '',
        'monthly_tokens' => 0,
        'max_schema_bytes' => $free->max_schema_bytes,
        'max_prompt_chars' => $free->max_prompt_chars,
        'lifetime_free_uses' => $free->lifetime_free_uses,
        'active' => false,
        'sort' => 0,
    ]);

    expect($free->refresh()->active)->toBeFalse()
        ->and(Plan::free()->slug)->toBe(Plan::FREE_SLUG)
        ->and(User::factory()->create()->effectivePlan()->slug)->toBe(Plan::FREE_SLUG);
});
