<?php

namespace Tests\Unit\BadgeForge\Endpoint;

use App\Support\BadgeForge\Endpoint\EndpointFetchException;
use App\Support\BadgeForge\Endpoint\EndpointFetchReason;
use App\Support\BadgeForge\Endpoint\EndpointFetchRequest;
use App\Support\BadgeForge\Endpoint\EndpointHttpClient;
use App\Support\BadgeForge\Endpoint\LimitedResponseSink;
use App\Support\BadgeForge\Endpoint\ResolvedEndpoint;
use App\Support\BadgeForge\Endpoint\ResponseBodyTooLarge;
use App\Support\BadgeForge\Endpoint\SafeEndpointUrl;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function endpointTestTarget(string $host = 'example.com', string $ip = '8.8.8.8'): ResolvedEndpoint
{
    return new ResolvedEndpoint(
        new SafeEndpointUrl('https://'.$host.'/badge.json', $host, 443),
        $ip,
    );
}

function endpointTestJson(array $body = []): string
{
    return json_encode(array_merge([
        'schemaVersion' => 1,
        'label' => 'build',
        'message' => 'passing',
    ], $body), JSON_THROW_ON_ERROR);
}

it('pina o DNS e desabilita redirect e proxy nas opcoes curl', function () {
    $options = (new EndpointHttpClient)->options(endpointTestTarget());

    expect($options['allow_redirects'])->toBeFalse()
        ->and($options['proxy'])->toBe('')
        ->and($options['protocols'])->toBe(['https'])
        ->and($options['curl'][CURLOPT_RESOLVE])->toBe(['example.com:443:8.8.8.8'])
        ->and($options['curl'])->not->toHaveKey(CURLOPT_NOPROXY)
        ->and($options['curl'])->not->toHaveKey(CURLOPT_PROTOCOLS)
        ->and($options['curl'])->not->toHaveKey(CURLOPT_FOLLOWLOCATION);
});

it('usa colchetes no IP IPv6 do pin', function () {
    $options = (new EndpointHttpClient)->options(
        endpointTestTarget('example.com', '2001:4860:4860::8888'),
    );

    expect($options['curl'][CURLOPT_RESOLVE])->toBe(['example.com:443:[2001:4860:4860::8888]']);
});

it('devolve payload e etag em um 200 valido', function () {
    Http::fake([
        '*' => Http::response(endpointTestJson(['color' => 'blue']), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'ETag' => '"abc123"',
        ]),
    ]);

    $result = (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));

    expect($result->notModified)->toBeFalse()
        ->and($result->etag)->toBe('"abc123"')
        ->and($result->payload?->label)->toBe('build')
        ->and($result->payload?->color)->toBe('blue');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.com/badge.json'
        && $request->hasHeader('Accept', 'application/json'));
});

it('trata 304 como nao modificado e envia If-None-Match', function () {
    Http::fake(['*' => Http::response('', 304)]);

    $result = (new EndpointHttpClient)->fetch(
        new EndpointFetchRequest(endpointTestTarget(), '"abc123"'),
    );

    expect($result->notModified)->toBeTrue()
        ->and($result->payload)->toBeNull()
        ->and($result->etag)->toBe('"abc123"');

    Http::assertSent(fn ($request): bool => $request->hasHeader('If-None-Match', '"abc123"'));
});

it('recusa redirect sem seguir', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://evil.example/badge.json'])]);

    try {
        (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));
        $this->fail('O redirect deveria ter sido recusado.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::Redirected);
    }
});

it('mapeia 429 com Retry-After para falha de limite da origem', function () {
    Http::fake(['*' => Http::response('', 429, ['Retry-After' => '30'])]);

    try {
        (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));
        $this->fail('O 429 deveria ter virado excecao.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::RateLimited)
            ->and($exception->retryAfter)->toBe(30);
    }
});

it('mapeia 5xx para unavailable', function () {
    Http::fake(['*' => Http::response('', 503)]);

    try {
        (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));
        $this->fail('O 503 deveria ter virado excecao.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::Unavailable)
            ->and($exception->retryAfter)->toBeNull();
    }
});

it('distingue timeout de indisponibilidade', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 5000 ms'));

    try {
        (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));
        $this->fail('O timeout deveria ter virado excecao.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::Timeout);
    }
});

it('mapeia falha de conexao para unavailable', function () {
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    try {
        (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));
        $this->fail('A conexao falha deveria ter virado excecao.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::Unavailable);
    }
});

it('recusa content-type fora de json', function () {
    Http::fake(['*' => Http::response('{"schemaVersion":1}', 200, ['Content-Type' => 'text/html'])]);

    try {
        (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));
        $this->fail('O content-type deveria ter sido recusado.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::UnsupportedContentType);
    }
});

it('aceita content-type +json', function () {
    Http::fake(['*' => Http::response(endpointTestJson(), 200, [
        'Content-Type' => 'application/vnd.shields+json',
    ])]);

    $result = (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));

    expect($result->payload?->message)->toBe('passing');
});

it('recusa corpo acima do teto mesmo sem Content-Length', function () {
    config(['badgeforge.endpoint.max_body_bytes' => 32]);

    Http::fake(['*' => Http::response(endpointTestJson(), 200, [
        'Content-Type' => 'application/json',
    ])]);

    try {
        (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));
        $this->fail('O corpo grande deveria ter sido recusado.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::TooLarge);
    }
});

it('recusa corpo grande anunciado pelo Content-Length', function () {
    config(['badgeforge.endpoint.max_body_bytes' => 32]);

    Http::fake(['*' => Http::response('{}', 200, [
        'Content-Type' => 'application/json',
        'Content-Length' => '9999',
    ])]);

    try {
        (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));
        $this->fail('O Content-Length deveria ter sido recusado.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::TooLarge);
    }
});

it('nao delega a resposta ao StreamHandler do Guzzle', function () {
    $options = (new EndpointHttpClient)->options(endpointTestTarget());

    expect($options)->not->toHaveKey('stream')
        ->and($options)->not->toHaveKey('handler');
});

it('aborta o sink acima de max_body_bytes sem Content-Length', function () {
    $sink = new LimitedResponseSink(
        Utils::streamFor(Utils::tryFopen('php://temp', 'w+')),
        5,
    );

    $sink->write('abcde');

    expect(fn () => $sink->write('f'))->toThrow(ResponseBodyTooLarge::class);
});

it('devolve invalid_response para json fora do contrato', function () {
    Http::fake(['*' => Http::response('{"schemaVersion":2}', 200, [
        'Content-Type' => 'application/json',
    ])]);

    try {
        (new EndpointHttpClient)->fetch(new EndpointFetchRequest(endpointTestTarget()));
        $this->fail('O schema invalido deveria ter virado excecao.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::InvalidResponse);
    }
});
