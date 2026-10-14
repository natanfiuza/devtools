<?php

use App\Models\User;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Rotas publicas: sempre prefixadas pelo idioma ({locale}), com a raiz "/"
| resolvendo a preferencia do visitante e redirecionando (docs/SPEC.md secao 3).
| Rotas internas (login, dashboard, settings) ficam sem prefixo e usam o
| idioma negociado.
*/

it('redireciona a raiz para o idioma do navegador', function () {
    $this->withHeader('Accept-Language', 'pt-BR,pt;q=0.9,en;q=0.8')
        ->get('/')
        ->assertRedirect('/pt-br');
});

it('redireciona a raiz para o fallback quando nao reconhece o idioma', function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
        ->get('/')
        ->assertRedirect('/en');
});

it('redireciona a raiz com o idioma do cookie antes do cabecalho', function () {
    $this->withUnencryptedCookie('locale', 'fr')
        ->withHeader('Accept-Language', 'pt-BR')
        ->get('/')
        ->assertRedirect('/fr');
});

it('redireciona a raiz com o idioma do usuario autenticado antes do cookie', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->withUnencryptedCookie('locale', 'fr')
        ->withHeader('Accept-Language', 'pt-BR')
        ->get('/')
        ->assertRedirect('/es');
});

it('preserva a query string no redirecionamento da raiz', function () {
    $response = $this->withHeader('Accept-Language', 'pt-PT')
        ->get('/?utm_source=newsletter&ref=home')
        ->assertRedirect();

    $location = $response->headers->get('Location');

    // A ordem dos parametros nao carrega significado: compara como conjunto.
    expect($location)->toBeString();

    $parts = parse_url((string) $location);

    expect($parts['path'] ?? null)->toBe('/pt-pt');

    parse_str($parts['query'] ?? '', $query);

    expect($query)->toEqual(['utm_source' => 'newsletter', 'ref' => 'home']);
});

it('renderiza a home no idioma da url', function () {
    $this->get('/pt-br')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('locale', 'pt_BR')
            ->where('localeSlug', 'pt-br')
            ->where('localeHtml', 'pt-BR')
            ->has('locales', count(Locales::codes()))
            ->has('translations.app')
            ->has('translations.home')
        );
});

it('atende as cinco urls publicas', function () {
    foreach (Locales::slugs() as $slug) {
        $this->get('/'.$slug)->assertOk();
    }
});

it('devolve 404 para um idioma inexistente', function () {
    $this->get('/de')->assertNotFound();
    $this->get('/pt')->assertNotFound();
});

it('guarda a preferencia da url para as proximas visitas', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->get('/es')
        ->assertOk()
        ->assertCookie('locale', 'es', false);

    expect($user->refresh()->locale)->toBe('es');
});

it('nao regrava a preferencia a cada visita', function () {
    $this->withUnencryptedCookie('locale', 'fr')
        ->get('/fr')
        ->assertOk();
});

it('usa o idioma negociado nas rotas sem prefixo', function () {
    $this->withHeader('Accept-Language', 'es-AR,es;q=0.9')
        ->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'es'));
});

it('declara hreflang de todos os idiomas na pagina publica', function () {
    $response = $this->get('/pt-br/styleguide')->assertOk();

    $html = $response->getContent();

    foreach (Locales::all() as $locale) {
        expect($html)->toContain('hreflang="'.$locale['html'].'"');
        expect($html)->toContain(url('/'.$locale['slug'].'/styleguide'));
    }

    expect($html)->toContain('hreflang="x-default"');
    expect($html)->toContain('<link rel="canonical" href="'.url('/pt-br/styleguide').'">');
});

it('nao declara hreflang em rota sem prefixo de idioma', function () {
    expect($this->get('/login')->getContent())->not->toContain('hreflang');
});
