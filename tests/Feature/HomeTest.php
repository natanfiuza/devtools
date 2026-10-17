<?php

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
            );
    }
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
