<?php

use App\Enums\AdminActionType;
use App\Enums\SubscriptionIntentPaymentMethod;
use App\Enums\SubscriptionIntentStatus;
use App\Models\AdminAction;
use App\Models\Plan;
use App\Models\SubscriptionIntent;
use App\Models\User;
use App\Notifications\SubscriptionIntentSubmitted;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('entrega a pagina de planos nos cinco idiomas', function () {
    foreach (Locales::all() as $code => $locale) {
        $this->get('/'.$locale['slug'].'/pricing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pricing')
                ->where('locale', $code)
                ->where('seo.noindex', false)
                ->has('plans', 3)
            );
    }
});

it('manda os planos ativos na ordem de exibicao, com o dado cru a vista da tela', function () {
    $this->get('/pt-br/pricing')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('plans.0.slug', 'free')
        ->where('plans.0.isFree', true)
        ->where('plans.0.interval', null)
        ->where('plans.0.schemaSize', '8 KB')
        ->where('plans.0.lifetimeFreeUses', '5')
        ->where('plans.1.slug', 'pro-monthly')
        ->where('plans.1.isFree', false)
        ->where('plans.1.interval', 'month')
        ->where('plans.2.slug', 'pro-yearly')
        ->where('plans.2.interval', 'year')
        ->has('plans.2.name')
    );
});

it('formata preco e numeros no idioma da pagina (8,90 e 89,00)', function () {
    $this->get('/pt-br/pricing')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('plans.1.price', "R$\u{00A0}8,90")
        ->where('plans.2.price', "R$\u{00A0}89,00")
        ->where('plans.1.monthlyTokens', '2.000.000')
        ->where('plans.1.promptChars', '4.000')
    );

    $this->get('/en/pricing')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('plans.1.price', 'R$8.90')
        ->where('plans.2.price', 'R$89.00')
        ->where('plans.1.monthlyTokens', '2,000,000')
    );
});

it('tira da pagina de planos o plano desativado', function () {
    Plan::query()->where('slug', 'pro-yearly')->update(['active' => false]);

    $this->get('/pt-br/pricing')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('plans', 2)
        ->where('plans.1.slug', 'pro-monthly')
    );
});

it('redireciona visitante sem conta para o cadastro e guarda url.intended para /subscribe', function () {
    $response = $this->withUnencryptedCookie('locale', 'pt-br')->get('/subscribe?plan=pro-yearly');

    $response->assertRedirect('/pt-br/register');
    expect(session('url.intended'))->toContain('/subscribe?plan=pro-yearly');
});

it('exibe o formulario de intencao de assinatura para usuario autenticado', function () {
    $user = User::factory()->create(['username' => 'natan_dev']);

    $this->actingAs($user)
        ->get('/subscribe?plan=pro-yearly')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subscribe')
            ->where('selectedPlan', 'pro-yearly')
            ->where('username', 'natan_dev')
            ->has('plans', 2)
        );
});

it('registra a intencao de assinatura, reserva o username e notifica o admin por e-mail', function () {
    Notification::fake();
    config()->set('legal.contact_email', 'vendas@exemplo.com');

    $user = User::factory()->create(['username' => null]);

    $this->actingAs($user)
        ->post(route('subscribe.store'), [
            'plan' => 'pro-monthly',
            'username' => 'Natan_Fiuza',
            'phone' => '(11) 99999-0000',
            'document' => '123.456.789-00',
            'payment_method' => SubscriptionIntentPaymentMethod::Pix->value,
            'notes' => 'Prefiro pagar via QR Code',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('subscribe', ['plan' => 'pro-monthly']));

    expect($user->refresh()->username)->toBe('natan_fiuza');

    $intent = SubscriptionIntent::query()->sole();

    expect($intent->user_id)->toBe($user->id)
        ->and($intent->username)->toBe('natan_fiuza')
        ->and($intent->phone)->toBe('(11) 99999-0000')
        ->and($intent->document)->toBe('123.456.789-00')
        ->and($intent->payment_method)->toBe(SubscriptionIntentPaymentMethod::Pix)
        ->and($intent->status)->toBe(SubscriptionIntentStatus::Pending);

    Notification::assertSentOnDemand(SubscriptionIntentSubmitted::class);
});

it('impede reservar um username ja usado por outra conta', function () {
    User::factory()->create(['username' => 'ocupado']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('subscribe.store'), [
            'plan' => 'pro-monthly',
            'username' => 'ocupado',
            'phone' => '11999990000',
            'document' => '12345678900',
            'payment_method' => SubscriptionIntentPaymentMethod::Pix->value,
        ])
        ->assertSessionHasErrors('username');
});

it('permite ao admin listar, concluir e cancelar intencoes de assinatura com auditoria', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['username' => 'dev_pro']);
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    $intent = SubscriptionIntent::query()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'username' => 'dev_pro',
        'phone' => '11999990000',
        'document' => '12345678900',
        'payment_method' => SubscriptionIntentPaymentMethod::Pix,
        'status' => SubscriptionIntentStatus::Pending,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.intents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Intents')
            ->has('intents.data', 1)
            ->where('intents.data.0.username', 'dev_pro')
        );

    $this->actingAs($admin)
        ->post(route('admin.intents.fulfill', $intent))
        ->assertRedirect();

    expect($intent->refresh()->status)->toBe(SubscriptionIntentStatus::Fulfilled);
    expect(AdminAction::query()->where('action', AdminActionType::SubscriptionIntentFulfilled)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.intents.cancel', $intent))
        ->assertRedirect();

    expect($intent->refresh()->status)->toBe(SubscriptionIntentStatus::Canceled);
    expect(AdminAction::query()->where('action', AdminActionType::SubscriptionIntentCanceled)->exists())->toBeTrue();
});
