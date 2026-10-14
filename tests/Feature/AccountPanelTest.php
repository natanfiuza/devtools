<?php

use App\Enums\NewsletterSource;
use App\Models\NewsletterSubscriber;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLedger;
use App\Models\User;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Area da conta (Bloco 5): visao geral, historico, faturas e exportacao.
|
| O que se prova aqui e o que cada tela recebe e que o dado de uma conta nao
| aparece na tela de outra. A exclusao por anonimizacao tem teste proprio em
| Settings/ProfileUpdateTest, junto do resto do ciclo da conta.
*/

/**
 * Inscricao na newsletter ligada ao e-mail da conta. `confirmed_at` esta fora do
 * #[Fillable] de proposito, entao o estado entra por forceFill.
 *
 * @param  array<string, mixed>  $estado
 */
function inscricaoDaConta(string $email, array $estado = []): NewsletterSubscriber
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

it('fecha a area da conta para visitante', function () {
    $this->get(route('dashboard.history'))->assertRedirect(route('login'));
    $this->get(route('dashboard.billing'))->assertRedirect(route('login'));
    $this->get(route('profile.export'))->assertRedirect(route('login'));
});

it('mostra o plano gratuito com a cota vitalicia de quem nao paga', function () {
    $user = User::factory()->create();

    UsageLedger::factory()->count(2)->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('plan.name', 'Free')
            ->where('plan.isFree', true)
            ->where('quota.unit', 'uses')
            ->where('quota.limit', 5)
            ->where('quota.used', 2)
            ->where('quota.remaining', 3)
            ->where('quota.exhausted', false)
            // Sem assinatura nao ha janela: o limite do Free e vitalicio.
            ->where('quota.windowStart', null)
            ->where('quota.windowEnd', null)
            ->where('subscription', null)
            ->where('payments', 0));
});

it('esgota a cota e mostra o aviso na visao geral', function () {
    $user = User::factory()->create();

    UsageLedger::factory()->count(5)->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quota.used', 5)
            ->where('quota.remaining', 0)
            ->where('quota.exhausted', true));
});

it('mostra o plano da assinatura vigente, com vencimento e cota em tokens', function () {
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    $user = User::factory()->create();

    $assinatura = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);

    UsageLedger::factory()->for($user)->create(['tokens' => 300]);

    Payment::factory()->for($user)->create([
        'subscription_id' => $assinatura->id,
        'plan_id' => $plano->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('plan.name', $plano->name)
            ->where('plan.isFree', false)
            ->where('plan.price', Format::money($plano->price_cents, $plano->currency))
            ->where('quota.unit', 'tokens')
            ->where('quota.limit', $plano->monthly_tokens)
            ->where('quota.used', 300)
            ->where('quota.remaining', $plano->monthly_tokens - 300)
            ->where('quota.windowStart', Format::date($assinatura->current_period_start))
            ->where('quota.windowEnd', Format::date($assinatura->current_period_end))
            ->where('subscription.plan', $plano->name)
            ->where('subscription.status', 'active')
            ->where('subscription.end', Format::date($assinatura->current_period_end))
            ->where('subscription.current', true)
            ->where('payments', 1));
});

it('nao conta o consumo de fora do periodo da assinatura', function () {
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    $user = User::factory()->create();

    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);

    UsageLedger::factory()->for($user)->create(['tokens' => 300]);
    UsageLedger::factory()->for($user)->create([
        'tokens' => 5000,
        'created_at' => now()->subMonth(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('quota.used', 300));
});

it('entrega o historico vazio enquanto nao ha consumo para listar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/History')
            ->has('localeSlug'));
});

it('lista so as faturas da propria conta', function () {
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    $user = User::factory()->create();

    $assinatura = Subscription::factory()->for($user)->for($plano)->create();

    Payment::factory()->for($user)->create([
        'subscription_id' => $assinatura->id,
        'plan_id' => $plano->id,
    ]);

    // Fatura de outra conta: nao pode aparecer na lista desta.
    Payment::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Billing')
            ->has('payments.data', 1)
            ->where('payments.total', 1)
            ->where('payments.data.0.amount', Format::money(2990, 'BRL'))
            ->where('payments.data.0.method', 'pix')
            ->where('payments.data.0.status', 'paid')
            ->where('payments.data.0.plan', $plano->name)
            ->where('subscription.plan', $plano->name)
            ->where('total', 1));
});

it('mostra a fatura estornada sem apagar o registro', function () {
    $user = User::factory()->create();

    Payment::factory()->for($user)->refunded()->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.status', 'refunded')
            ->where('total', 1));
});

it('pagina as faturas de vinte em vinte', function () {
    $user = User::factory()->create();

    Payment::factory()->count(21)->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 20)
            ->where('payments.current_page', 1)
            ->where('payments.last_page', 2)
            ->where('total', 21));

    $this->actingAs($user)
        ->get(route('dashboard.billing', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('payments.current_page', 2));
});

it('nao tem assinatura para mostrar quando a conta esta no Free', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscription', null)
            ->has('payments.data', 0));
});

it('exporta os dados da conta em JSON para download', function () {
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    $user = User::factory()->create([
        'name' => 'Ana Souza',
        'email' => 'ana@exemplo.com',
        'locale' => 'pt_BR',
        'appearance' => 'dark',
    ]);

    $assinatura = Subscription::factory()->for($user)->for($plano)->create();

    Payment::factory()->for($user)->create([
        'subscription_id' => $assinatura->id,
        'plan_id' => $plano->id,
        'note' => 'Pix recebido',
    ]);

    UsageLedger::factory()->for($user)->create(['tokens' => 300]);
    inscricaoDaConta('ana@exemplo.com', ['confirmed_at' => now()]);

    $response = $this->actingAs($user)->get(route('profile.export'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
        ->assertDownload('devtools-dados-'.$user->id.'-'.now()->format('Y-m-d').'.json');

    $data = $response->json();

    expect($data)->toBeArray()
        ->and($data['exported_at'])->not->toBeNull()
        ->and($data['account']['name'])->toBe('Ana Souza')
        ->and($data['account']['email'])->toBe('ana@exemplo.com')
        ->and($data['account']['locale'])->toBe('pt_BR')
        ->and($data['account']['appearance'])->toBe('dark')
        // Credencial nao sai no arquivo: nao ha copia da senha fora do banco.
        ->and($data['account'])->not->toHaveKeys(['password', 'remember_token'])
        ->and($data['newsletter']['email'])->toBe('ana@exemplo.com')
        ->and($data['subscriptions'])->toHaveCount(1)
        ->and($data['subscriptions'][0]['plan'])->toBe($plano->name)
        ->and($data['payments'])->toHaveCount(1)
        ->and($data['payments'][0]['amount_cents'])->toBe(2990)
        ->and($data['payments'][0]['method'])->toBe('pix')
        ->and($data['usage'])->toHaveCount(1)
        ->and($data['usage'][0]['tokens'])->toBe(300);
});

it('exporta os dados de quem nao tem inscricao nem vinculo social', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('profile.export'));

    $data = $response->assertOk()->json();

    expect($data['social_accounts'])->toBe([])
        ->and($data['newsletter'])->toBeNull()
        ->and($data['subscriptions'])->toBe([])
        ->and($data['payments'])->toBe([])
        ->and($data['usage'])->toBe([]);
});
