<?php

use Illuminate\Support\Facades\Log;

/*
| Endpoint publico da badge estatica (SPEC-BADGEFORGE §6, ADR-076).
|
| Cobre o contrato HTTP: content-type, cache, ETag/304, 404 de segmento
| invalido, decode do texto e ausencia de log do conteudo.
*/

it('serve o SVG com content-type, cache e ETag', function () {
    $response = $this->get('/api/badges/static/build/passing.svg')->assertOk();

    $svg = (string) $response->getContent();

    expect($response->headers->get('Content-Type'))->toBe('image/svg+xml; charset=utf-8')
        ->and($response->headers->get('Cache-Control'))->toBe('max-age=86400, public')
        ->and($response->headers->get('ETag'))->toBe('"'.hash('sha256', $svg).'"')
        ->and($svg)->toStartWith('<svg xmlns=')
        ->and($svg)->toContain('role="img"')
        ->and($svg)->toContain('aria-label="build: passing"')
        ->and($svg)->toContain('<title>build: passing</title>')
        ->and($svg)->not->toContain("\n");
});

it('respeita e limita o cacheSeconds', function () {
    $this->get('/api/badges/static/build/passing.svg?cacheSeconds=600')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=600, public');

    $this->get('/api/badges/static/build/passing.svg?cacheSeconds=100')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public');

    $this->get('/api/badges/static/build/passing.svg?cacheSeconds=999999')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=86400, public');

    $this->get('/api/badges/static/build/passing.svg?cacheSeconds=abc')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=86400, public');
});

it('mantem o ETag estavel entre requisicoes equivalentes e muda com o conteudo', function () {
    $first = $this->get('/api/badges/static/build/passing.svg')->getContent();
    $same = $this->get('/api/badges/static/build/passing.svg?style=flat&color=lightgrey&logoSize=10')->getContent();
    $different = $this->get('/api/badges/static/build/passing.svg?color=blue')->getContent();

    expect($same)->toBe($first)
        ->and($different)->not->toBe($first);
});

it('responde 304 sem corpo e sem content-type quando o ETag bate', function () {
    $etag = (string) $this->get('/api/badges/static/build/passing.svg')->headers->get('ETag');

    $response = $this->withHeaders(['If-None-Match' => $etag])
        ->get('/api/badges/static/build/passing.svg');

    $response->assertStatus(304);

    expect($response->getContent())->toBe('')
        ->and($response->headers->has('Content-Type'))->toBeFalse()
        ->and($response->headers->get('ETag'))->toBe($etag)
        ->and($response->headers->get('Cache-Control'))->toBe('max-age=86400, public');
});

it('aceita ETag fraco no If-None-Match', function () {
    $etag = (string) $this->get('/api/badges/static/build/passing.svg')->headers->get('ETag');

    $this->withHeaders(['If-None-Match' => 'W/'.$etag])
        ->get('/api/badges/static/build/passing.svg')
        ->assertStatus(304);
});

it('responde 404 em JSON para segmento invalido ou formato nao suportado', function (string $url) {
    $this->get($url)
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
})->with([
    'sem extensao' => '/api/badges/static/build/passing',
    'png na F1' => '/api/badges/static/build/passing.png',
    'extensao em caixa alta' => '/api/badges/static/build/passing.SVG',
    'barra simples' => '/api/badges/static/ci%2Fcd/passing.svg',
    'segmento vazio' => '/api/badges/static/build//passing.svg',
]);

it('casa mensagens com ponto, porcentagem e espaco codificado', function (string $url, string $text) {
    $svg = (string) $this->get($url)->assertOk()->getContent();

    expect($svg)->toContain('<title>'.$text.'</title>');
})->with([
    'versao com pontos' => ['/api/badges/static/version/1.0.0.svg', 'version: 1.0.0'],
    'porcentagem' => ['/api/badges/static/coverage/98%25.svg', 'coverage: 98%'],
    'decimal' => ['/api/badges/static/php/24.0.svg', 'php: 24.0'],
    'underscore vira espaco' => ['/api/badges/static/hello_world/passing.svg', 'hello world: passing'],
]);

it('decodifica a barra dupla-codificada no texto', function () {
    $svg = (string) $this->get('/api/badges/static/ci%252Fcd/passing.svg')->assertOk()->getContent();

    expect($svg)->toContain('<title>ci/cd: passing</title>')
        ->and($svg)->toContain('aria-label="ci/cd: passing"');
});

it('cai no padrao para estilo ou cor invalidos e ignora logo desconhecido', function () {
    $default = (string) $this->get('/api/badges/static/build/passing.svg')->getContent();
    $invalid = (string) $this->get('/api/badges/static/build/passing.svg?style=foo&color=not-a-color&logo=nope')->getContent();

    expect($invalid)->toBe($default);
});

it('renderiza o logo registrado e ignora javascript no link', function () {
    $withLogo = (string) $this->get('/api/badges/static/build/passing.svg?logo=github')->getContent();
    $withoutLogo = (string) $this->get('/api/badges/static/build/passing.svg')->getContent();

    expect($withLogo)->not->toBe($withoutLogo)
        ->and($withLogo)->toContain('<path');

    $noAnchor = (string) $this->get('/api/badges/static/build/passing.svg?link=javascript:alert(1)')->getContent();

    expect($noAnchor)->not->toContain('<a ');
});

it('escapa o texto e o link no documento', function () {
    $svg = (string) $this->get('/api/badges/static/%3Cscript%3Ealert(1)%3C%252Fscript%3E/passing.svg')->getContent();
    $linked = (string) $this->get('/api/badges/static/build/passing.svg?link=https%3A%2F%2Fexample.com%2F%3Fx%3D1%26y%3D2')->getContent();

    expect($svg)->not->toContain('<script')
        ->and($svg)->toContain('&lt;script&gt;')
        ->and($linked)->toContain('href="https://example.com/?x=1&amp;y=2"')
        ->and($linked)->toContain('rel="noopener noreferrer"');
});

it('serve HEAD sem corpo e com ETag', function () {
    $response = $this->head('/api/badges/static/build/passing.svg')->assertOk();

    expect($response->getContent())->toBe('')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

it('mantem os headers de seguranca globais', function () {
    $this->get('/api/badges/static/build/passing.svg')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('nao registra o conteudo do badge em log', function () {
    Log::spy();

    $this->get('/api/badges/static/segredo/confidencial.svg')->assertOk();

    foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
        Log::shouldNotHaveReceived($level);
    }
});

it('gera a URL nomeada da rota', function () {
    expect(route('badges.static', ['label' => 'build', 'message' => 'passing', 'ext' => 'svg']))
        ->toBe(url('/api/badges/static/build/passing.svg'));
});
