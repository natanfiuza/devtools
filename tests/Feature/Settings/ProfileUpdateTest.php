<?php

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('displays the profile page', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

it('updates the profile information', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'username' => 'Test_User_01',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test_user_01', $user->username);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

it('troca o idioma da conta e passa a interface para ele', function () {
    $user = User::factory()->create(['locale' => 'pt_BR']);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => 'fr',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertCookie('locale', 'fr', false)
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->locale)->toBe('fr');
});

it('recusa um idioma que nao existe', function () {
    $user = User::factory()->create(['locale' => 'pt_BR']);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => 'tlh',
        ]);

    $response->assertSessionHasErrors('locale');

    expect($user->refresh()->locale)->toBe('pt_BR');
});

it('nao mexe no idioma da conta quando o campo nao vem', function () {
    $user = User::factory()->create(['locale' => 'pt_BR']);

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->locale)->toBe('pt_BR');
});

it('keeps the email verification status unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $this->assertNotNull($user->refresh()->email_verified_at);
});

it('anonimiza a conta ao excluir, preservando pagamentos e assinaturas', function () {
    $plano = Plan::query()->where('slug', 'pro-monthly')->sole();
    $user = User::factory()->create([
        'name' => 'Ana Souza',
        'email' => 'ana@exemplo.com',
        'locale' => 'pt_BR',
    ]);

    $assinatura = Subscription::factory()->for($user)->for($plano)->create();

    $pagamento = Payment::factory()->for($user)->create([
        'subscription_id' => $assinatura->id,
        'plan_id' => $plano->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('root'));

    $this->assertGuest();

    $anonima = $user->refresh();

    expect($anonima->name)->toBe('')
        ->and($anonima->email)->toBe('removido+'.$user->id.'@removido.invalid')
        ->and($anonima->password)->toBeNull()
        ->and($anonima->locale)->toBeNull()
        ->and($anonima->anonymized_at)->not->toBeNull();

    // O que a contabilidade precisa fica: nota fiscal nao se apaga (ADR-028).
    expect($pagamento->refresh()->user_id)->toBe($user->id)
        ->and($pagamento->status)->toBe(PaymentStatus::Paid)
        ->and($anonima->payments()->count())->toBe(1)
        ->and($anonima->subscriptions()->count())->toBe(1)
        ->and($assinatura->refresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and($anonima->activeSubscription())->toBeNull();
});

it('derruba as sessoes abertas ao excluir a conta', function () {
    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'sessao-da-ana',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password']);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('nao deixa a conta anonimizada entrar de novo', function () {
    $user = User::factory()->create(['email' => 'sumiu@exemplo.com']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password']);

    // O endereco antigo nao existe mais em lugar nenhum, e a senha tambem nao.
    $this->post(route('login.store'), [
        'email' => 'sumiu@exemplo.com',
        'password' => 'password',
    ]);

    $this->assertGuest();

    expect(User::query()->where('email', 'sumiu@exemplo.com')->exists())->toBeFalse();
});

it('requires the correct password to delete the account', function () {
    $user = User::factory()->create(['email' => 'ana@exemplo.com']);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    // Sem a senha certa nada acontece: nem anonimizacao, nem saida da sessao.
    $this->assertAuthenticated();

    expect($user->refresh()->email)->toBe('ana@exemplo.com')
        ->and($user->anonymized_at)->toBeNull();
});
