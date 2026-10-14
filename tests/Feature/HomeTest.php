<?php

use App\Models\Plan;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Home publica (F1).
|
| O hero, os chips e o grid de tools sao montados no cliente a partir do
| registro em resources/js/tools - coberto por ToolsRegistryTest e pelo vitest
| (home.test.ts). O que se verifica aqui e o que so o servidor sabe: o catalogo
| da tabela `plans` no idioma da requisicao (nada de preco nem de limite no
| codigo) e o contato da venda manual (ADR-027).
*/

it('entrega a home nos cinco idiomas', function () {
    foreach (Locales::all() as $code => $locale) {
        $this->get('/'.$locale['slug'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('locale', $code)
                ->where('seo.noindex', false)
                ->has('plans', 3)
            );
    }
});

it('manda os planos ativos na ordem de exibicao, com o dado cru a vista da tela', function () {
    $this->get('/pt-br')->assertOk()->assertInertia(fn (Assert $page) => $page
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

it('formata preco e numeros no idioma da pagina', function () {
    $this->get('/pt-br')->assertOk()->assertInertia(fn (Assert $page) => $page
        // O espaco entre simbolo e numero e o estreito do Intl (U+00A0).
        ->where('plans.1.price', "R$\u{00A0}29,90")
        ->where('plans.1.monthlyTokens', '2.000.000')
        ->where('plans.1.promptChars', '4.000')
    );

    $this->get('/en')->assertOk()->assertInertia(fn (Assert $page) => $page
        // A moeda e do dado (BRL); quem muda com o idioma e o numero.
        ->where('plans.1.price', 'R$29.90')
        ->where('plans.1.monthlyTokens', '2,000,000')
    );
});

it('tira do site o plano desativado', function () {
    Plan::query()->where('slug', 'pro-yearly')->update(['active' => false]);

    $this->get('/pt-br')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('plans', 2)
        ->where('plans.1.slug', 'pro-monthly')
    );
});

it('manda o contato da venda manual e aceita a ausencia dele', function () {
    config()->set('legal.contact_email', 'vendas@exemplo.com');

    $this->get('/pt-br')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('contactEmail', 'vendas@exemplo.com')
    );

    config()->set('legal.contact_email', '');

    $this->get('/pt-br')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('contactEmail', null)
    );
});

it('tem os textos da home nos cinco idiomas', function () {
    $chaves = [
        'home.title',
        'home.subtitle',
        'home.tools.title',
        'home.tools.subtitle',
        'home.tools.filter',
        'home.tools.all',
        'home.tools.empty',
        'home.ai.badge',
        'home.ai.title',
        'home.ai.body',
        'home.ai.note',
        'home.ai.preview_prompt',
        'home.ai.preview_sql',
        'home.cta.register',
        'home.cta.contact',
        'home.pricing.title',
        'home.pricing.subtitle',
        'home.pricing.free',
        'home.pricing.interval.month',
        'home.pricing.interval.year',
        'home.pricing.features.uses',
        'home.pricing.features.tokens',
        'home.pricing.features.schema',
        'home.pricing.features.prompt',
    ];

    foreach (array_keys(Locales::all()) as $code) {
        foreach ($chaves as $chave) {
            expect(trans($chave, [], $code))->not->toBe($chave);
        }
    }
});

it('escreve as linhas do plano com os marcadores do vue-i18n', function () {
    // O servidor manda o numero ja formatado e o texto cru de lang/: quem
    // interpola e o vue-i18n, no cliente - por isso a marcacao e `{count}` e
    // nao `:count` (que e a do trans() do PHP).
    expect(trans('home.pricing.features.tokens', [], 'pt_BR'))->toContain('{count}');
    expect(trans('home.pricing.features.uses', [], 'en'))->toContain('{count}');
    expect(trans('home.pricing.features.prompt', [], 'es'))->toContain('{count}');
    expect(trans('home.pricing.features.schema', [], 'fr'))->toContain('{size}');
});
