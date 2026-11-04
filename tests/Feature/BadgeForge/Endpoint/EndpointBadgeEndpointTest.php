<?php

use App\Models\BadgeEndpoint;
use App\Models\User;
use App\Support\BadgeForge\Endpoint\EndpointCacheKey;
use App\Support\BadgeForge\Endpoint\HostResolver;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/*
| Rota publica da badge de endpoint (F5, ADR-083/ADR-084).
|
| A identificacao e so o ULID; a URL de origem nunca aparece na resposta, nas
| props ou no cache. Falha de origem vira stale/fallback 200, nunca 500.
*/

function endpointFakeDns(array $ips = ['93.184.216.34']): void
{
    app()->instance(HostResolver::class, new class($ips) implements HostResolver
    {
        /**
         * @param  list<string>  $ips
         */
        public function __construct(private readonly array $ips) {}

        public function resolve(string $host): array
        {
            return $this->ips;
        }
    });
}

function endpointCreate(string $url = 'https://example.com/badge.json'): BadgeEndpoint
{
    return BadgeEndpoint::factory()
        ->for(User::factory()->create())
        ->create(['name' => 'deploys', 'url' => $url]);
}

function endpointPublicUrl(BadgeEndpoint $endpoint, string $ext = 'svg'): string
{
    return '/api/badges/endpoint/'.$endpoint->id.'.'.$ext;
}

function endpointFakeJson(array $overrides = []): string
{
    return json_encode(array_merge([
        'schemaVersion' => 1,
        'label' => 'deploys',
        'message' => 'green',
    ], $overrides), JSON_THROW_ON_ERROR);
}

it('serve a badge e alterna MISS/HIT com TTL fixo', function () {
    endpointFakeDns();

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(endpointFakeJson(), 200, [
            'Content-Type' => 'application/json',
        ]),
    ]);

    $endpoint = endpointCreate();

    $first = $this->get(endpointPublicUrl($endpoint))->assertOk();

    expect($first->headers->get('Content-Type'))->toBe('image/svg+xml; charset=utf-8')
        ->and($first->headers->get('X-Badge-Cache'))->toBe('MISS')
        ->and((string) $first->getContent())->toContain('>deploys<')
        ->and((string) $first->getContent())->toContain('>green<')
        // 300 s vem da config; `?cacheSeconds=` e `?refresh=1` sao ignorados.
        ->and($first->headers->get('Cache-Control'))->toContain('max-age=300');

    $second = $this->get(endpointPublicUrl($endpoint, 'svg').'?cacheSeconds=99999&refresh=1')
        ->assertOk();

    expect($second->headers->get('X-Badge-Cache'))->toBe('HIT');

    Http::assertSentCount(1);
});

it('aplica cosmeticos da query vencendo os defaults do JSON', function () {
    endpointFakeDns();
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(endpointFakeJson([
            'color' => 'brightgreen',
            'labelColor' => 'black',
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    $endpoint = endpointCreate();

    $defaults = $this->get(endpointPublicUrl($endpoint))->assertOk();

    expect((string) $defaults->getContent())->toContain('#44cc11');

    $overridden = $this->get(endpointPublicUrl($endpoint, 'svg').'?label=build&color=ff0000&labelColor=00ff00')
        ->assertOk();

    expect((string) $overridden->getContent())->toContain('>build<')
        ->and((string) $overridden->getContent())->toContain('#ff0000')
        ->and((string) $overridden->getContent())->toContain('#00ff00');
});

it('serve PNG, download e 304 por If-None-Match', function () {
    endpointFakeDns();
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(endpointFakeJson(), 200, [
            'Content-Type' => 'application/json',
            'ETag' => '"endpoint-1"',
        ]),
    ]);

    $endpoint = endpointCreate();

    $svg = $this->get(endpointPublicUrl($endpoint))->assertOk();

    $download = $this->get(endpointPublicUrl($endpoint, 'svg').'?download=1')->assertOk();

    expect((string) $download->headers->get('Content-Disposition'))->toStartWith('attachment;');

    $etag = (string) $svg->headers->get('ETag');

    $this->withHeaders(['If-None-Match' => $etag])
        ->get(endpointPublicUrl($endpoint))
        ->assertStatus(304);

    if (extension_loaded('imagick')) {
        $png = $this->get(endpointPublicUrl($endpoint, 'png'))->assertOk();

        expect($png->headers->get('Content-Type'))->toBe('image/png')
            ->and(substr((string) $png->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    }
});

it('responde 404 para ULID desconhecido ou extensao invalida', function () {
    $this->get('/api/badges/endpoint/01JZZZZZZZZZZZZZZZZZZZZZZZ.svg')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $endpoint = endpointCreate();

    $this->get('/api/badges/endpoint/'.$endpoint->id.'.gif')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('nunca devolve 500: falha de origem vira fallback 200', function () {
    endpointFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response('', 503)]);

    $endpoint = endpointCreate();

    $response = $this->get(endpointPublicUrl($endpoint))->assertOk();

    expect((string) $response->getContent())->toContain('>deploys<')
        ->and((string) $response->getContent())->toContain('>unavailable<')
        ->and($response->headers->get('X-Badge-Cache'))->toBe('MISS')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=60');
});

it('serve stale quando a origem falha depois do TTL', function () {
    endpointFakeDns();
    $fail = false;

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => function () use (&$fail) {
            return $fail
                ? Http::response('', 503)
                : Http::response(endpointFakeJson(), 200, [
                    'Content-Type' => 'application/json',
                ]);
        },
    ]);

    $endpoint = endpointCreate();

    $this->get(endpointPublicUrl($endpoint))->assertOk();
    $this->travel(301)->seconds();
    $fail = true;

    $stale = $this->get(endpointPublicUrl($endpoint))->assertOk();

    expect($stale->headers->get('X-Badge-Cache'))->toBe('STALE')
        ->and($stale->headers->get('Warning'))->toBe('110 - "Response is Stale"')
        ->and((string) $stale->getContent())->toContain('>green<');
});

it('revalida com If-None-Match e trata 304 como HIT', function () {
    endpointFakeDns();
    $calls = 0;

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => function ($request) use (&$calls) {
            $calls++;

            if ($calls === 1) {
                return Http::response(endpointFakeJson(), 200, [
                    'Content-Type' => 'application/json',
                    'ETag' => '"e1"',
                ]);
            }

            return $request->hasHeader('If-None-Match', '"e1"')
                ? Http::response('', 304)
                : Http::response('', 500);
        },
    ]);

    $endpoint = endpointCreate();

    $this->get(endpointPublicUrl($endpoint))->assertOk();
    $this->travel(301)->seconds();

    $second = $this->get(endpointPublicUrl($endpoint))->assertOk();

    expect($second->headers->get('X-Badge-Cache'))->toBe('HIT')
        ->and((string) $second->getContent())->toContain('>green<');
});

it('responde 422 unsafe_url quando a URL guardada nao passa no guard', function () {
    $endpoint = endpointCreate();

    BadgeEndpoint::query()->whereKey($endpoint->getKey())->update(['url' => 'http://example.com/badge.json']);

    $this->get(endpointPublicUrl($endpoint))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'unsafe_url');
});

it('responde fallback 200 quando o DNS deixa de resolver', function () {
    endpointFakeDns([]);

    $endpoint = endpointCreate();

    $response = $this->get(endpointPublicUrl($endpoint))->assertOk();

    expect((string) $response->getContent())->toContain('>unavailable<');
});

it('nao persiste a URL de origem nem o corpo cru no cache', function () {
    endpointFakeDns();
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(endpointFakeJson([
            'description' => 'conteudo cru que nao deve ir ao cache',
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    $endpoint = endpointCreate();
    $this->get(endpointPublicUrl($endpoint))->assertOk();

    $cached = Cache::get(EndpointCacheKey::for($endpoint->url));
    $encoded = json_encode($cached);

    expect($cached)->toBeArray()
        ->and($encoded)->not->toContain('conteudo cru')
        ->and($encoded)->not->toContain('example.com')
        ->and($encoded)->not->toContain($endpoint->url);
});

it('degrada para fallback quando outra requisicao tem o lock', function () {
    endpointFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response(endpointFakeJson(), 200)]);

    $endpoint = endpointCreate();
    $lock = Cache::lock(EndpointCacheKey::for($endpoint->url).':lock', 10);

    expect($lock)->toBeInstanceOf(Lock::class)
        ->and($lock->get())->toBeTrue();

    try {
        $response = $this->get(endpointPublicUrl($endpoint))->assertOk();

        expect((string) $response->getContent())->toContain('>unavailable<');
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});

it('busca direto quando o store de cache esta fora do ar', function () {
    endpointFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response(endpointFakeJson(), 200, [
        'Content-Type' => 'application/json',
    ])]);

    $this->instance('cache', Mockery::mock(Repository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('get')->andThrow(new RuntimeException('cache down'));
        $mock->shouldReceive('put')->andThrow(new RuntimeException('cache down'));
        $mock->shouldReceive('lock')->andThrow(new RuntimeException('cache down'));
    }));

    $endpoint = endpointCreate();

    $response = $this->get(endpointPublicUrl($endpoint))->assertOk();

    expect($response->headers->get('X-Badge-Cache'))->toBe('MISS')
        ->and((string) $response->getContent())->toContain('>green<');
});

it('mantem a rota estatica vencendo a de endpoint', function () {
    $response = $this->get('/api/badges/static/build/passing.svg')->assertOk();

    expect((string) $response->getContent())->toContain('>build<')
        ->and((string) $response->getContent())->toContain('>passing<');
});
