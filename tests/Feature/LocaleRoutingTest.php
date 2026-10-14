<?php

use App\Models\User;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Rotas publicas e telas de acesso: sempre prefixadas pelo idioma ({locale}),
| com a raiz "/" resolvendo a preferencia do visitante e redirecionando
| (docs/SPEC.md secao 3, ADR-022). Painel (dashboard, settings) e rotas de
| maquina (auth/{provider}/...) ficam sem prefixo e usam o idioma negociado.
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

it('redireciona a raiz com o idioma de navegacao antes do idioma da conta', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->withUnencryptedCookie('locale', 'fr')
        ->withHeader('Accept-Language', 'pt-BR')
        ->get('/')
        ->assertRedirect('/fr');
});

it('redireciona a raiz com o idioma da conta quando nao ha cookie', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
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

it('guarda o idioma de navegacao no cookie sem tocar no idioma da conta', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->get('/es')
        ->assertOk()
        ->assertCookie('locale', 'es', false);

    // Navegar por /es muda o idioma de navegacao (cookie). O idioma da conta -
    // o dos e-mails - so muda quando a pessoa muda, no cadastro ou no perfil
    // (ADR-026).
    expect($user->refresh()->locale)->toBe('en');
});

it('nao regrava a preferencia a cada visita', function () {
    $this->withUnencryptedCookie('locale', 'fr')
        ->get('/fr')
        ->assertOk();
});

it('usa o idioma negociado nas rotas sem prefixo', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'es'));
});

it('segue o cookie antes do idioma da conta nas rotas sem prefixo', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->withUnencryptedCookie('locale', 'fr')
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));
});

it('negocia o idioma do cabecalho nas rotas sem prefixo', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->withHeader('Accept-Language', 'es-AR,es;q=0.9')
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'es'));
});

it('nao atende as telas de acesso sem o prefixo de idioma', function () {
    $this->get('/login')->assertNotFound();
    $this->get('/register')->assertNotFound();
    $this->get('/forgot-password')->assertNotFound();
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

it('nao declara hreflang nas telas de acesso', function () {
    // Elas tem prefixo de idioma, mas nao entram no indice: sem hreflang, com
    // canonical da propria URL e um robots noindex (App\Support\Seo).
    $html = $this->get('/pt-br/login')->assertOk()->getContent();

    expect($html)->not->toContain('hreflang');
    expect($html)->toContain('<meta name="robots" content="noindex, follow">');
    expect($html)->toContain('<link rel="canonical" href="'.url('/pt-br/login').'">');
});

it('nao declara hreflang nas paginas de token da newsletter', function () {
    expect($this->get('/pt-br/newsletter/confirm/nao-existe')->getContent())
        ->not->toContain('hreflang');
});
