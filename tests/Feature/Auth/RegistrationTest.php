<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

it('renders the registration screen', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

it('registers new users', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

it('guarda o idioma escolhido no cadastro', function () {
    // A pagina esta em pt-br; a pessoa escolheu frances para a conta.
    $this->withUnencryptedCookie('locale', 'pt-br')
        ->post(route('register.store', ['locale' => 'pt-br']), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'locale' => 'fr',
        ]);

    expect(User::query()->where('email', 'test@example.com')->sole()->locale)->toBe('fr');
});

it('cai no idioma da pagina quando o cadastro nao escolhe idioma', function () {
    $this->post(route('register.store', ['locale' => 'pt-br']), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::query()->where('email', 'test@example.com')->sole()->locale)->toBe('pt_BR');
});

it('nao falha o cadastro por causa de um idioma desconhecido', function () {
    $this->post(route('register.store', ['locale' => 'es']), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'tlh', // klingon
    ]);

    $this->assertAuthenticated();

    expect(User::query()->where('email', 'test@example.com')->sole()->locale)->toBe('es');
});
