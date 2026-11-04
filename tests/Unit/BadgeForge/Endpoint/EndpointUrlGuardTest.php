<?php

use App\Support\BadgeForge\Endpoint\EndpointUrlException;
use App\Support\BadgeForge\Endpoint\EndpointUrlGuard;
use App\Support\BadgeForge\Endpoint\EndpointUrlReason;
use Tests\TestCase;

uses(TestCase::class);

it('canonicaliza esquema, host, porta e path default', function () {
    $url = (new EndpointUrlGuard)->parse('HTTPS://Example.COM.:443/badge.json?x=1');

    expect($url->canonical)->toBe('https://example.com/badge.json?x=1')
        ->and($url->host)->toBe('example.com')
        ->and($url->port)->toBe(443);

    expect((new EndpointUrlGuard)->parse('https://example.com')->canonical)
        ->toBe('https://example.com/');
});

it('aceita IPv4 literal para a classificacao posterior', function () {
    $url = (new EndpointUrlGuard)->parse('https://8.8.8.8/status.json');

    expect($url->host)->toBe('8.8.8.8')
        ->and($url->canonical)->toBe('https://8.8.8.8/status.json');
});

it('recusa URL insegura com o motivo certo', function (string $raw, EndpointUrlReason $reason) {
    try {
        (new EndpointUrlGuard)->parse($raw);
        $this->fail('A URL deveria ter sido recusada.');
    } catch (EndpointUrlException $exception) {
        expect($exception->reason)->toBe($reason);
    }
})->with([
    'http' => ['http://example.com/badge.json', EndpointUrlReason::Scheme],
    'porta' => ['https://example.com:8443/badge.json', EndpointUrlReason::Port],
    'userinfo' => ['https://user:pass@example.com/badge.json', EndpointUrlReason::UserInfo],
    'localhost' => ['https://localhost/badge.json', EndpointUrlReason::Host],
    'rotulo unico' => ['https://intranet/badge.json', EndpointUrlReason::Host],
    'underline' => ['https://exa_mple.com/badge.json', EndpointUrlReason::Host],
    'ipv6 literal' => ['https://[::1]/badge.json', EndpointUrlReason::Host],
    'espaco' => ['https://example.com/badge .json', EndpointUrlReason::Chars],
    'fragmento' => ['https://example.com/badge.json#x', EndpointUrlReason::Chars],
    'barra invertida' => ['https://example.com\\badge.json', EndpointUrlReason::Chars],
    'vazio' => ['   ', EndpointUrlReason::Length],
    'longa' => ['https://example.com/'.str_repeat('a', 3000), EndpointUrlReason::Length],
]);
