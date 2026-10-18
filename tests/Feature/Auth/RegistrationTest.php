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

it('exibe mensagem de validacao de tamanho minimo de senha traduzida nos cinco idiomas', function (string $slug, string $expectedMessage) {
    $response = $this->from('/'.$slug.'/register')->post(route('register.store', ['locale' => $slug]), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => '1234567',
        'password_confirmation' => '1234567',
    ]);

    $response->assertSessionHasErrors([
        'password' => $expectedMessage,
    ]);
})->with([
    'pt-br' => ['pt-br', 'O campo senha deve ter pelo menos 8 caracteres.'],
    'pt-pt' => ['pt-pt', 'O campo palavra-passe deve ter pelo menos 8 carateres.'],
    'en' => ['en', 'The password field must be at least 8 characters.'],
    'es' => ['es', 'El campo contraseña debe tener al menos 8 caracteres.'],
    'fr' => ['fr', 'Le champ mot de passe doit contenir au moins 8 caractères.'],
]);

it('exibe mensagem de confirmacao de senha incorreta traduzida nos cinco idiomas', function (string $slug, string $expectedMessage) {
    $response = $this->from('/'.$slug.'/register')->post(route('register.store', ['locale' => $slug]), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'diferente123',
    ]);

    $response->assertSessionHasErrors([
        'password' => $expectedMessage,
    ]);
})->with([
    'pt-br' => ['pt-br', 'A confirmação do campo senha não confere.'],
    'pt-pt' => ['pt-pt', 'A confirmação do campo palavra-passe não coincide.'],
    'en' => ['en', 'The password field confirmation does not match.'],
    'es' => ['es', 'La confirmación del campo contraseña no coincide.'],
    'fr' => ['fr', 'La confirmation du champ mot de passe ne correspond pas.'],
]);

it('exibe mensagem de campo obrigatorio traduzida nos cinco idiomas', function (string $slug, string $expectedMessage) {
    $response = $this->from('/'.$slug.'/register')->post(route('register.store', ['locale' => $slug]), [
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertSessionHasErrors([
        'name' => $expectedMessage,
    ]);
})->with([
    'pt-br' => ['pt-br', 'O campo nome é obrigatório.'],
    'pt-pt' => ['pt-pt', 'O campo nome é obrigatório.'],
    'en' => ['en', 'The name field is required.'],
    'es' => ['es', 'El campo nombre es obligatorio.'],
    'fr' => ['fr', 'Le champ nom est obligatoire.'],
]);
