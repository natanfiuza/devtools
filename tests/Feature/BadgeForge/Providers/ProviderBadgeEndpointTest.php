<?php

use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\CachedProviderPayload;
use App\Support\BadgeForge\Providers\ProviderCacheKey;
use App\Support\BadgeForge\Providers\ProviderCatalog;
use App\Support\BadgeForge\Providers\ProviderUrlBuilder;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function providerEndpoint(string $provider, string $definition, string $path, string $ext = 'svg', array $query = []): string
{
    $url = '/api/badges/'.$provider.'/'.$definition.'/'.$path.'.'.$ext;

    return $query === [] ? $url : $url.'?'.http_build_query($query);
}

function fakeAllProviderApis(): void
{
    Http::preventStrayRequests();

    Http::fake([
        'api.github.com/*' => Http::response([
            'stargazers_count' => 1234,
            'forks_count' => 56,
            'open_issues_count' => 7,
            'license' => ['spdx_id' => 'MIT'],
            'description' => 'conteudo cru que nao deve ir ao cache',
        ], 200, ['ETag' => '"gh1"']),
        'registry.npmjs.org/*' => Http::response(['version' => '18.2.0']),
        'api.npmjs.org/*' => Http::response(['downloads' => 1_234_567]),
        'https://packagist.org/*' => Http::response(['downloads' => ['total' => 98_765]]),
        'https://repo.packagist.org/*' => Http::response([
            'packages' => ['laravel/framework' => [['version' => 'v11.0.0']]],
        ]),
        'hub.docker.com/*' => Http::response(['pull_count' => 1_000_000_000]),
    ]);
}

it('serve as 9 metricas com uma unica chamada por URL', function () {
    fakeAllProviderApis();

    $cases = [
        ['github', 'stars', 'natanfiuza/devtools', 'stars', '1.2k', 'MISS'],
        ['github', 'forks', 'natanfiuza/devtools', 'forks', '56', 'HIT'],
        ['github', 'issues', 'natanfiuza/devtools', 'issues', '7', 'HIT'],
        ['github', 'license', 'natanfiuza/devtools', 'license', 'MIT', 'HIT'],
        ['npm', 'v', 'vue', 'npm', '18.2.0', 'MISS'],
        ['npm', 'dm', 'vue', 'downloads', '1.2M', 'MISS'],
        ['packagist', 'dt', 'laravel/framework', 'downloads', '98.7k', 'MISS'],
        ['packagist', 'v', 'laravel/framework', 'version', 'v11.0.0', 'MISS'],
        ['docker', 'pulls', 'library/postgres', 'pulls', '1B', 'MISS'],
    ];

    foreach ($cases as [$provider, $definition, $path, $label, $message, $status]) {
        $response = $this->get(providerEndpoint($provider, $definition, $path))->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('image/svg+xml; charset=utf-8')
            ->and($response->headers->get('X-Badge-Cache'))->toBe($status)
            ->and((string) $response->getContent())->toContain('>'.$label.'<')
            ->and((string) $response->getContent())->toContain('>'.$message.'<');
    }

    // GitHub compartilha a entrada pelas 4 definicoes; npm/packagist tem URLs
    // distintas por metrica e docker tem uma so.
    Http::assertSentCount(6);

    Http::assertSent(fn ($request): bool => ! $request->hasHeader('Authorization'));
});

it('aplica cosmeticos, PNG e download', function () {
    fakeAllProviderApis();

    $svg = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools', 'svg', [
        'labelColor' => 'ff0000',
        'color' => '00ff00',
        'logo' => 'github',
        'link' => 'https://example.com',
        'cacheSeconds' => 999999,
        'refresh' => '1',
    ]))->assertOk();

    expect((string) $svg->getContent())->toContain('#ff0000')
        ->and((string) $svg->getContent())->toContain('https://example.com')
        // TTL do provider vence o `cacheSeconds` do cliente e `refresh` e ignorado.
        ->and($svg->headers->get('Cache-Control'))->toContain('max-age=300');

    $download = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools', 'svg', ['download' => '1']));

    expect((string) $download->headers->get('Content-Disposition'))->toStartWith('attachment;');

    if (extension_loaded('imagick')) {
        $png = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools', 'png'))->assertOk();

        expect($png->headers->get('Content-Type'))->toBe('image/png')
            ->and(substr((string) $png->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    }
});

it('responde 404 para provider, definicao, path ou item desabilitado', function () {
    fakeAllProviderApis();

    $this->get('/api/badges/nope/stars/a/b.svg')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->get('/api/badges/github/nope/a/b.svg')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->get('/api/badges/github/stars/owner/too/many.svg')
        ->assertNotFound();

    $this->get('/api/badges/github/stars/a.svg')
        ->assertNotFound();

    BadgeProviderDefinition::query()->where('slug', 'v')->update(['enabled' => false]);
    BadgeProvider::query()->where('slug', 'docker')->update(['enabled' => false]);
    app()->forgetInstance(ProviderCatalog::class);

    $this->get('/api/badges/npm/v/vue.svg')->assertNotFound();
    $this->get('/api/badges/docker/pulls/library/postgres.svg')->assertNotFound();
});

it('nunca devolve 500: falha vira fallback 200 com TTL curto', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response('', 500)]);

    $response = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools'))->assertOk();

    expect((string) $response->getContent())->toContain('>unavailable<')
        ->and($response->headers->get('X-Badge-Cache'))->toBe('MISS')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=60');
});

it('serve stale quando o provider falha depois do TTL', function () {
    $fail = false;

    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/*' => function () use (&$fail) {
            return $fail
                ? Http::response('', 503)
                : Http::response([
                    'stargazers_count' => 1234,
                    'forks_count' => 56,
                    'open_issues_count' => 7,
                    'license' => ['spdx_id' => 'MIT'],
                ]);
        },
    ]);

    $first = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools'))->assertOk();

    expect($first->headers->get('X-Badge-Cache'))->toBe('MISS');

    $this->travel(301)->seconds();
    $fail = true;

    $stale = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools'))->assertOk();

    expect($stale->headers->get('X-Badge-Cache'))->toBe('STALE')
        ->and($stale->headers->get('Warning'))->toBe('110 - "Response is Stale"')
        ->and((string) $stale->getContent())->toContain('>1.2k<');
});

it('revalida com If-None-Match e trata 304 como HIT', function () {
    $calls = 0;

    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/*' => function ($request) use (&$calls) {
            $calls++;

            if ($calls === 1) {
                return Http::response([
                    'stargazers_count' => 1234,
                    'forks_count' => 56,
                    'open_issues_count' => 7,
                    'license' => ['spdx_id' => 'MIT'],
                ], 200, ['ETag' => '"gh1"']);
            }

            return $request->hasHeader('If-None-Match', '"gh1"')
                ? Http::response('', 304)
                : Http::response('', 500);
        },
    ]);

    $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools'))->assertOk();
    $this->travel(301)->seconds();

    $second = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools'))->assertOk();

    expect($second->headers->get('X-Badge-Cache'))->toBe('HIT')
        ->and((string) $second->getContent())->toContain('>1.2k<');
});

it('refaz o fetch quando a entrada de cache esta corrompida', function () {
    fakeAllProviderApis();

    $pair = app(ProviderCatalog::class)->pair('github', 'stars');
    $url = app(ProviderUrlBuilder::class)->build($pair, [
        'owner' => 'natanfiuza',
        'repo' => 'devtools',
    ]);
    Cache::put(ProviderCacheKey::for($pair, $url, null), 'corrupted', 300);

    $response = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools'))->assertOk();

    expect($response->headers->get('X-Badge-Cache'))->toBe('MISS')
        ->and((string) $response->getContent())->toContain('>1.2k<');
});

it('nao persiste o corpo cru do provider', function () {
    fakeAllProviderApis();

    $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools'))->assertOk();

    $pair = app(ProviderCatalog::class)->pair('github', 'stars');
    $url = app(ProviderUrlBuilder::class)->build($pair, ['owner' => 'natanfiuza', 'repo' => 'devtools']);
    $cached = Cache::get(ProviderCacheKey::for($pair, $url, null));

    expect($cached)->toBeArray()
        ->and(json_encode($cached))->not->toContain('conteudo cru')
        ->and(CachedProviderPayload::fromArray($cached)?->metrics)->toBe([
            'stars' => 1234,
            'forks' => 56,
            'issues' => 7,
            'license' => 'MIT',
        ]);
});

it('degradou para fallback quando outra requisicao tem o lock', function () {
    fakeAllProviderApis();

    $pair = app(ProviderCatalog::class)->pair('github', 'stars');
    $url = app(ProviderUrlBuilder::class)->build($pair, ['owner' => 'natanfiuza', 'repo' => 'devtools']);
    $lock = Cache::lock(ProviderCacheKey::for($pair, $url, null).':lock', 10);

    expect($lock)->toBeInstanceOf(Lock::class)
        ->and($lock->get())->toBeTrue();

    try {
        $response = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools'))->assertOk();

        expect((string) $response->getContent())->toContain('>unavailable<');
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});

it('busca direto quando o store de cache esta fora do ar', function () {
    fakeAllProviderApis();

    $this->instance('cache', Mockery::mock(Repository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('get')->andThrow(new RuntimeException('cache down'));
        $mock->shouldReceive('put')->andThrow(new RuntimeException('cache down'));
        $mock->shouldReceive('lock')->andThrow(new RuntimeException('cache down'));
    }));

    $response = $this->get(providerEndpoint('github', 'stars', 'natanfiuza/devtools'))->assertOk();

    expect($response->headers->get('X-Badge-Cache'))->toBe('MISS')
        ->and((string) $response->getContent())->toContain('>1.2k<');
});

it('mantem a rota estatica vencendo a de provider', function () {
    Http::preventStrayRequests();

    $response = $this->get('/api/badges/static/build/passing.svg')->assertOk();

    expect((string) $response->getContent())->toContain('>build<')
        ->and((string) $response->getContent())->toContain('>passing<');

    Http::assertNothingSent();
    expect(route('badges.static', ['label' => 'a', 'message' => 'b', 'ext' => 'svg']))
        ->toContain('/api/badges/static/a/b.svg');
});
