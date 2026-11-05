<?php

use App\Enums\CustomMetricFormatter;
use App\Models\BadgeCustomProvider;
use App\Support\BadgeForge\Endpoint\HostResolver;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Rota publica do provider customizado (F7, ADR-087).
|
| Stateless e opaca: so o ULID identifica, a origem nunca sai do servidor.
| Desconhecido/desabilitado/definicao/path errado responde 404; falha da
| origem vira fallback 200 e nunca 500.
*/

function customBadgeFakeDns(array $ips = ['93.184.216.34']): void
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

function customBadgeFakeJson(array $overrides = []): void
{
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(json_encode(array_merge([
            'stargazers_count' => 42,
            'forks_count' => 7,
        ], $overrides), JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @param  list<array<string, mixed>>|null  $metrics
 */
function customBadgeProvider(array $overrides = [], ?array $metrics = null): BadgeCustomProvider
{
    $provider = BadgeCustomProvider::factory()->create($overrides);

    foreach ($metrics ?? [
        ['slug' => 'stars', 'label' => 'Stars', 'pointer' => '/stargazers_count'],
        ['slug' => 'forks', 'label' => 'Forks', 'pointer' => '/forks_count'],
    ] as $sort => $metric) {
        $provider->definitions()->create(array_merge([
            'formatter' => CustomMetricFormatter::Number,
            'sort' => $sort,
        ], $metric));
    }

    return $provider->refresh();
}

function customBadgeUrl(
    BadgeCustomProvider $provider,
    string $definition = 'stars',
    string $path = 'natanfiuza/devtools',
    string $ext = 'svg',
): string {
    return '/api/badges/custom/'.$provider->id.'/'.$definition.'/'.$path.'.'.$ext;
}

it('serve a badge e alterna MISS/HIT com TTL fixo', function () {
    customBadgeFakeDns();
    customBadgeFakeJson();

    $provider = customBadgeProvider();

    $first = $this->get(customBadgeUrl($provider))->assertOk();

    expect($first->headers->get('Content-Type'))->toBe('image/svg+xml; charset=utf-8')
        ->and($first->headers->get('X-Badge-Cache'))->toBe('MISS')
        ->and((string) $first->getContent())->toContain('>Stars<')
        ->and((string) $first->getContent())->toContain('>42<')
        // 300 s vem da config; `?cacheSeconds=` e `?refresh=1` sao ignorados.
        ->and($first->headers->get('Cache-Control'))->toContain('max-age=300');

    $second = $this->get(customBadgeUrl($provider).'?cacheSeconds=99999&refresh=1')
        ->assertOk();

    expect($second->headers->get('X-Badge-Cache'))->toBe('HIT')
        ->and((string) $second->getContent())->toContain('>42<');

    Http::assertSentCount(1);
});

it('extrai todos os pointers de uma unica resposta', function () {
    customBadgeFakeDns();
    customBadgeFakeJson();

    $provider = customBadgeProvider();

    $this->get(customBadgeUrl($provider, 'stars'))->assertOk();
    $forks = $this->get(customBadgeUrl($provider, 'forks'))->assertOk();

    expect($forks->headers->get('X-Badge-Cache'))->toBe('HIT')
        ->and((string) $forks->getContent())->toContain('>Forks<')
        ->and((string) $forks->getContent())->toContain('>7<');

    Http::assertSentCount(1);
});

it('aplica cosmeticos da query vencendo o label da definicao', function () {
    customBadgeFakeDns();
    customBadgeFakeJson();

    $provider = customBadgeProvider();

    $response = $this->get(customBadgeUrl($provider).'?label=build&color=ff0000&labelColor=00ff00')
        ->assertOk();

    expect((string) $response->getContent())->toContain('>build<')
        ->and((string) $response->getContent())->not->toContain('>Stars<')
        ->and((string) $response->getContent())->toContain('#ff0000')
        ->and((string) $response->getContent())->toContain('#00ff00');
});

it('responde 404 para ULID, definicao, path ou extensao invalidos', function () {
    customBadgeFakeDns();
    customBadgeFakeJson();

    $provider = customBadgeProvider();
    $disabled = customBadgeProvider(['enabled' => false]);

    $this->get('/api/badges/custom/01JZZZZZZZZZZZZZZZZZZZZZZZ/stars/natanfiuza/devtools.svg')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->get(customBadgeUrl($disabled))->assertNotFound();

    $this->get(customBadgeUrl($provider, 'coverage'))->assertNotFound();

    $this->get(customBadgeUrl($provider, 'stars', 'natanfiuza'))->assertNotFound();
    $this->get(customBadgeUrl($provider, 'stars', 'natanfiuza/dev%40tools'))->assertNotFound();
    $this->get(customBadgeUrl($provider, 'stars', 'natanfiuza/devtools', 'gif'))->assertNotFound();
});

it('nunca devolve 500: falha de origem vira fallback 200', function () {
    customBadgeFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response('', 503)]);

    $provider = customBadgeProvider();

    $response = $this->get(customBadgeUrl($provider))->assertOk();

    expect((string) $response->getContent())->toContain('>Stars<')
        ->and((string) $response->getContent())->toContain('>'.config('badgeforge.custom_providers.unavailable_message').'<')
        ->and($response->headers->get('X-Badge-Cache'))->toBe('MISS')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=60');
});

it('serve stale quando a origem falha depois do TTL', function () {
    customBadgeFakeDns();
    $fail = false;

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => function () use (&$fail) {
            return $fail
                ? Http::response('', 503)
                : Http::response(json_encode([
                    'stargazers_count' => 42,
                    'forks_count' => 7,
                ], JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
        },
    ]);

    $provider = customBadgeProvider();

    $this->get(customBadgeUrl($provider))->assertOk();
    $this->travel(301)->seconds();
    $fail = true;

    $stale = $this->get(customBadgeUrl($provider))->assertOk();

    expect($stale->headers->get('X-Badge-Cache'))->toBe('STALE')
        ->and($stale->headers->get('Warning'))->toBe('110 - "Response is Stale"')
        ->and((string) $stale->getContent())->toContain('>42<');
});

it('revalida com If-None-Match e trata 304 como HIT', function () {
    customBadgeFakeDns();
    $calls = 0;

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => function ($request) use (&$calls) {
            $calls++;

            if ($calls === 1) {
                return Http::response(json_encode([
                    'stargazers_count' => 42,
                    'forks_count' => 7,
                ], JSON_THROW_ON_ERROR), 200, [
                    'Content-Type' => 'application/json',
                    'ETag' => '"c1"',
                ]);
            }

            return $request->hasHeader('If-None-Match', '"c1"')
                ? Http::response('', 304)
                : Http::response('', 500);
        },
    ]);

    $provider = customBadgeProvider();

    $this->get(customBadgeUrl($provider))->assertOk();
    $this->travel(301)->seconds();

    $second = $this->get(customBadgeUrl($provider))->assertOk();

    expect($second->headers->get('X-Badge-Cache'))->toBe('HIT')
        ->and((string) $second->getContent())->toContain('>42<');
});

it('serve PNG, download e 304 por If-None-Match', function () {
    customBadgeFakeDns();
    customBadgeFakeJson();

    $provider = customBadgeProvider();

    $svg = $this->get(customBadgeUrl($provider))->assertOk();

    $download = $this->get(customBadgeUrl($provider).'?download=1')->assertOk();

    expect((string) $download->headers->get('Content-Disposition'))->toStartWith('attachment;');

    $etag = (string) $svg->headers->get('ETag');

    $this->withHeaders(['If-None-Match' => $etag])
        ->get(customBadgeUrl($provider))
        ->assertStatus(304);

    if (extension_loaded('imagick')) {
        $png = $this->get(customBadgeUrl($provider, 'stars', 'natanfiuza/devtools', 'png'))->assertOk();

        expect($png->headers->get('Content-Type'))->toBe('image/png')
            ->and(substr((string) $png->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    }
});

it('degrada para fallback quando outra requisicao tem o lock', function () {
    customBadgeFakeDns();
    customBadgeFakeJson();

    $provider = customBadgeProvider();

    // A chave e derivada do ULID, versao e URL resolvida. Depois do primeiro
    // acesso o cache existe, mas esta vencido; segurar o lock forca o STALE.
    $this->get(customBadgeUrl($provider))->assertOk();
    $this->travel(301)->seconds();

    $lockKey = 'badgeforge:custom:v1:'.$provider->id.':1:'.hash('sha256', 'https://example.com/repos/natanfiuza/devtools.json').':lock';

    // O TTL do lock conta no relogio viajado; por isso ele e adquirido depois
    // do `travel`, ja com a entrada de cache vencida mas ainda utilizavel.
    $lock = Cache::lock($lockKey, 10);

    expect($lock)->toBeInstanceOf(Lock::class)
        ->and($lock->get())->toBeTrue();

    try {
        $response = $this->get(customBadgeUrl($provider))->assertOk();

        expect($response->headers->get('X-Badge-Cache'))->toBe('STALE');
    } finally {
        $lock->release();
    }
});

it('nao persiste a origem nem o corpo cru no cache', function () {
    customBadgeFakeDns();
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(json_encode([
            'stargazers_count' => 42,
            'forks_count' => 7,
            'description' => 'conteudo cru que nao deve ir ao cache',
        ], JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']),
    ]);

    $provider = customBadgeProvider();

    $this->get(customBadgeUrl($provider))->assertOk();

    $key = 'badgeforge:custom:v1:'.$provider->id.':1:'.hash('sha256', 'https://example.com/repos/natanfiuza/devtools.json');
    $cached = Cache::get($key);
    $encoded = json_encode($cached);

    expect($cached)->toBeArray()
        ->and($encoded)->not->toContain('conteudo cru')
        ->and($encoded)->not->toContain('example.com');
});
