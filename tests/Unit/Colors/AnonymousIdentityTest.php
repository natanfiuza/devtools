<?php

use App\Support\Colors\AnonymousIdentity;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

/*
| Identidade anonima das curtidas (F6, ADR-135).
|
| O valor e aleatorio, o cookie e first-party e HttpOnly, e o IP nunca entra
| no banco: a chave do limitador e HMAC e nao pode ser confundida com o IP.
*/

it('emite um identificador aleatorio de 64 hexadecimais', function () {
    $first = AnonymousIdentity::issue();
    $second = AnonymousIdentity::issue();

    expect($first)->toMatch('/^[a-f0-9]{64}$/')
        ->and($second)->toMatch('/^[a-f0-9]{64}$/')
        ->and($first)->not->toBe($second);
});

it('le apenas um cookie no formato esperado', function () {
    $request = Request::create('/');
    $request->cookies->set(AnonymousIdentity::COOKIE, str_repeat('a', 64));

    expect(AnonymousIdentity::read($request))->toBe(str_repeat('a', 64));

    $request->cookies->set(AnonymousIdentity::COOKIE, 'curto');

    expect(AnonymousIdentity::read($request))->toBeNull();
});

it('devolve null quando nao ha cookie', function () {
    expect(AnonymousIdentity::read(Request::create('/')))->toBeNull();
});

it('cria a chave do limitador em HMAC, sem o IP em texto', function () {
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9']);

    $key = AnonymousIdentity::ipKey($request);

    expect($key)->toMatch('/^[a-f0-9]{64}$/')
        ->and($key)->not->toContain('203.0.113.9')
        ->and(AnonymousIdentity::ipKey($request))->toBe($key);
});

it('cria o cookie HttpOnly, Lax e com um ano de validade', function () {
    $cookie = AnonymousIdentity::cookie(str_repeat('b', 64));
    $days = (int) config('colors.social.cookie_days', 365);

    expect($cookie->getName())->toBe(AnonymousIdentity::COOKIE)
        ->and($cookie->getValue())->toBe(str_repeat('b', 64))
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBeGreaterThan(time() + ($days - 1) * 86400)
        ->and($cookie->getExpiresTime())->toBeLessThan(time() + ($days + 1) * 86400);
});
