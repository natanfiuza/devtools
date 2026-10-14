<?php

use App\Support\Consent;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Consentimento e medicao (ADR-013).
|
| A decisao e resolvida no servidor, pelo cookie, e vai pronta nas props
| compartilhadas - e o que impede o banner de piscar para quem ja respondeu.
| Sem cookie (ou com valor que nao serve) a decisao e nula, que e o estado
| seguro: nada de medicao.
|
| A tag do GA nao passa por aqui: ela so existe no cliente, depois do aceite.
| O que se verifica neste arquivo e justamente isso - que o HTML do servidor
| nao tem uma linha de medicao.
*/

it('nao tem decisao enquanto nao ha cookie', function () {
    $this->get('/pt-br')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.consent', null)
        ->where('analytics.cookie', 'consent')
        ->where('analytics.days', 180)
    );
});

it('le a decisao do cookie, aceita ou recusada', function () {
    foreach (Consent::values() as $decision) {
        $this->withUnencryptedCookie('consent', $decision)
            ->get('/pt-br')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('analytics.consent', $decision));
    }
});

it('volta a sem decisao com um cookie que nao serve, e o apaga', function () {
    // Valor desconhecido: cookie antigo ou editado a mao.
    $lixo = $this->withUnencryptedCookie('consent', 'talvez')->get('/pt-br')->assertOk();

    $lixo->assertInertia(fn (Assert $page) => $page->where('analytics.consent', null));
    // O middleware tira o valor invalido do navegador, senao ele volta sempre.
    $lixo->assertCookieExpired('consent');

    // Cookie repetido chega como array: tambem cai em "sem decisao".
    $repetido = $this->call('GET', '/pt-br', [], ['consent' => ['granted', 'denied']])
        ->assertOk();

    $repetido->assertInertia(fn (Assert $page) => $page->where('analytics.consent', null));
    $repetido->assertCookieExpired('consent');
});

it('nao manda cookie de limpeza quando a decisao e valida', function () {
    $response = $this->withUnencryptedCookie('consent', Consent::DENIED)
        ->get('/pt-br')
        ->assertOk();

    $response->assertCookieMissing('consent');
});

it('so manda o id do GA quando ele esta configurado', function () {
    config()->set('analytics.ga4_id', '');

    $this->get('/pt-br')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.ga4Id', null)
    );

    config()->set('analytics.ga4_id', 'G-TESTE123');

    $this->get('/pt-br')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.ga4Id', 'G-TESTE123')
    );
});

it('trata id em branco como medicao desligada', function () {
    config()->set('analytics.ga4_id', '   ');

    $this->get('/pt-br')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.ga4Id', null)
    );
});

it('nao deixa nenhuma tag de medicao no HTML', function () {
    config()->set('analytics.ga4_id', 'G-TESTE123');

    // Com aceite e com o id configurado: ainda assim, nada de GA no servidor.
    $html = $this->withUnencryptedCookie('consent', Consent::GRANTED)
        ->get('/pt-br')
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('googletagmanager')
        ->not->toContain('dataLayer')
        ->not->toContain('gtag(');
});

it('tem os textos do banner nos cinco idiomas', function () {
    $chaves = [
        'app.consent.title',
        'app.consent.body',
        'app.consent.accept',
        'app.consent.reject',
        'app.consent.details',
        'app.consent.necessary',
        'app.consent.necessary_body',
        'app.consent.analytics',
        'app.consent.analytics_body',
        'app.consent.privacy',
        'app.footer.cookies',
    ];

    foreach (array_keys(Locales::all()) as $code) {
        foreach ($chaves as $chave) {
            expect(trans($chave, [], $code))->not->toBe($chave);
        }
    }
});
