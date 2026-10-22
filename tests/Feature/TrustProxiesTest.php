<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
| Proxies confiaveis (ADR-052).
|
| Todo trafego em producao chega por um proxy reverso, que fala HTTP com o PHP
| e repassa o esquema original em X-Forwarded-Proto. Sem confiar no proxy, o
| Laravel acharia que a requisicao e http: canonical, OG e links sairiam com o
| esquema errado e o HSTS nunca seria emitido. A lista vem de
| `security.trusted_proxies` (TRUSTED_PROXIES) e e vazia por padrao: quem nao
| configurou nao passa a confiar em header nenhum.
*/

beforeEach(function () {
    config([
        'security.force_https' => false,
        'security.hsts.max_age' => 31536000,
        'security.csp.mode' => 'off',
    ]);
});

function trustProxiesRequest(): TestResponse
{
    return test()->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-For' => '203.0.113.9',
    ])->get('http://localhost/pt-br');
}

it('respeita o esquema encaminhado por um proxy confiavel', function () {
    config(['security.trusted_proxies' => ['127.0.0.1']]);

    $response = trustProxiesRequest()->assertOk();

    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains')
        ->and($response->getContent())
        ->toContain('<link rel="canonical" href="https://localhost/pt-br">');
});

it('ignora o esquema encaminhado por um proxy fora da lista', function () {
    config(['security.trusted_proxies' => ['10.0.0.1']]);

    $response = trustProxiesRequest()->assertOk();

    expect($response->headers->get('Strict-Transport-Security'))->toBeNull()
        ->and($response->getContent())
        ->toContain('<link rel="canonical" href="http://localhost/pt-br">');
});

it('ignora X-Forwarded-* quando nenhum proxy esta configurado', function () {
    config(['security.trusted_proxies' => []]);

    $response = trustProxiesRequest()->assertOk();

    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('le a lista da config a cada requisicao', function () {
    // O framework fixa a lista no boot; este middleware le a config por
    // request, entao mudar TRUSTED_PROXIES nao exige reiniciar o processo.
    config(['security.trusted_proxies' => []]);
    expect(trustProxiesRequest()->headers->get('Strict-Transport-Security'))->toBeNull();

    config(['security.trusted_proxies' => ['127.0.0.1']]);
    expect(trustProxiesRequest()->headers->get('Strict-Transport-Security'))->not->toBeNull();
});
