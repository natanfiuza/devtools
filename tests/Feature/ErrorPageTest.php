<?php

use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| 404 customizada (F7): a pagina `Error` do Inertia sai com o PublicLayout, no
| idioma do prefixo da URL, com props compartilhadas (i18n) e sempre noindex.
|
| Dois caminhos chegam nela: o fallback de rotas (URL que nao existe) e o
| handler de excecao (controller que aborta 404, como a tool inexistente).
| A API nunca recebe HTML.
*/

it('responde a URL desconhecida com a pagina de erro no idioma', function () {
    $this->get('/pt-br/nao-existe')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('localeSlug', 'pt-br'));
});

it('sai noindex, sem card, sem hreflang e sem JSON-LD', function () {
    $html = $this->get('/pt-br/nao-existe')->assertNotFound()->getContent();

    expect($html)
        ->toContain('<meta name="robots" content="noindex, follow">')
        ->toContain('<title>'.e(Seo::documentTitle((string) trans('errors.not_found.title', [], 'pt_BR'))).'</title>')
        ->not->toContain('property="og:')
        ->not->toContain('name="twitter:')
        ->not->toContain('application/ld+json')
        ->not->toContain('hreflang=');
});

it('traduz a pagina de erro pelo prefixo da URL', function () {
    foreach (['en', 'pt-br', 'pt-pt', 'fr', 'es'] as $slug) {
        // O prefixo da URL e o slug; o titulo vem do idioma resolvido.
        $locale = Locales::fromSlug($slug);

        expect($locale)->not->toBeNull();

        $html = $this->get('/'.$slug.'/nao-existe')->assertNotFound()->getContent();

        expect($html)
            ->toContain('lang="'.Locales::html($locale).'"')
            ->toContain('<title>'.e(Seo::documentTitle((string) trans('errors.not_found.title', [], $locale))).'</title>');
    }
});

it('usa a pagina de erro tambem quando o controller aborta 404', function () {
    // /tools/{slug} casa com a rota, mas a tool nao existe: quem responde e o
    // handler de excecao, nao o fallback.
    $this->get('/pt-br/tools/nao-existe')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404));
});

it('responde a URL desconhecida fora de um idioma com o idioma preferido', function () {
    $this->get('/nao-existe')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404));
});

it('deixa a 404 da API em JSON', function () {
    $response = $this->getJson('/api/v1/nao-existe')->assertNotFound();

    expect($response->headers->get('content-type'))->toContain('application/json')
        ->and($response->getContent())->not->toContain('data-page');
});
