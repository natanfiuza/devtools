<?php

use App\Models\User;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Telas de acesso sob o prefixo de idioma (ADR-022).
|
| Os nomes de rota continuam os do Fortify (login, register, password.request):
| o {locale} e resolvido pelo URL::defaults do middleware ResolveLocale, entao
| route('login') funciona dentro e fora da requisicao. Sem prefixo, elas nao
| existem (ver tests/Feature/LocaleRoutingTest.php).
*/

it('atende o login nos cinco idiomas', function () {
    foreach (Locales::all() as $code => $locale) {
        $this->get('/'.$locale['slug'].'/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('locale', $code)
                ->where('localeSlug', $locale['slug'])
            );
    }
});

it('preserva os nomes de rota de autenticacao', function () {
    expect(route('login'))->toBe(url('/en/login'))
        ->and(route('register'))->toBe(url('/en/register'))
        ->and(route('password.request'))->toBe(url('/en/forgot-password'))
        ->and(route('login', ['locale' => 'pt-br']))->toBe(url('/pt-br/login'));
});

it('guarda o idioma da url no cookie, e nao na conta', function () {
    $this->get('/fr/login')->assertOk()->assertCookie('locale', 'fr', false);

    $user = User::factory()->create(['locale' => 'en']);

    // Pagina publica: o login tem o middleware guest, que tira o usuario
    // autenticado da tela (302) antes da preferencia ser gravada.
    $this->actingAs($user)->get('/es')->assertOk();

    // Navegar e do cookie (idioma de navegacao); a conta so muda quando a
    // pessoa muda (ADR-026).
    expect($user->refresh()->locale)->toBe('en');
});

it('autentica pelo formulario no idioma da url', function () {
    $user = User::factory()->create();

    $this->post('/pt-br/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('manda o visitante sem sessao para o login do idioma negociado', function () {
    $this->withHeader('Accept-Language', 'pt-BR,pt;q=0.9')
        ->get('/dashboard')
        ->assertRedirect('/pt-br/login');
});

it('manda para o login do idioma padrao quando nao ha preferencia', function () {
    $this->get('/dashboard')->assertRedirect('/en/login');
});

it('manda o usuario sem e-mail verificado para a tela do idioma dele', function () {
    $user = User::factory()->unverified()->create(['locale' => 'fr']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/fr/email/verify');
});

it('pede noindex nas telas de acesso e mantem a home indexavel', function () {
    $this->get('/pt-br/login')
        ->assertInertia(fn (Assert $page) => $page->where('seo.noindex', true));

    $this->get('/pt-br/register')
        ->assertInertia(fn (Assert $page) => $page->where('seo.noindex', true));

    $this->get('/pt-br')
        ->assertInertia(fn (Assert $page) => $page->where('seo.noindex', false));
});
