<?php

use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\Clients\DockerHubProviderClient;
use App\Support\BadgeForge\Providers\Clients\GithubProviderClient;
use App\Support\BadgeForge\Providers\Clients\NpmProviderClient;
use App\Support\BadgeForge\Providers\Clients\PackagistProviderClient;
use App\Support\BadgeForge\Providers\ProviderCredential;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;
use App\Support\BadgeForge\Providers\ProviderMetricFormatter;
use App\Support\BadgeForge\Providers\ProviderPair;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function clientDefinition(string $clientKey, string $extractorKey, string $slug): BadgeProviderDefinition
{
    return new BadgeProviderDefinition([
        'provider_id' => 1,
        'slug' => $slug,
        'name' => $slug,
        'label' => $slug,
        'client_key' => $clientKey,
        'extractor_key' => $extractorKey,
        'path_pattern' => '{owner}/{repo}',
        'required_params' => [
            ['name' => 'owner', 'pattern' => '.+', 'allowSlash' => false, 'placeholderKey' => 'owner'],
            ['name' => 'repo', 'pattern' => '.+', 'allowSlash' => false, 'placeholderKey' => 'repo'],
        ],
        'url_template' => 'https://example.test/{owner}/{repo}',
        'supports_private' => true,
        'example_path' => 'a/b',
        'enabled' => true,
    ]);
}

function clientPair(string $clientKey, string $extractorKey, string $slug): ProviderPair
{
    $provider = new BadgeProvider([
        'slug' => $clientKey,
        'name' => $clientKey,
        'category' => 'vcs',
        'enabled' => true,
        'requires_auth' => true,
    ]);

    return new ProviderPair($provider, clientDefinition($clientKey, $extractorKey, $slug));
}

function clientRequest(
    ProviderPair $pair,
    string $url,
    ?ProviderCredential $credential = null,
    ?string $etag = null,
): ProviderFetchRequest {
    return new ProviderFetchRequest(
        pair: $pair,
        params: ['owner' => 'natanfiuza', 'repo' => 'devtools', 'vendor' => 'laravel', 'package' => 'framework'],
        url: $url,
        credential: $credential,
        etag: $etag,
    );
}

function githubClient(): GithubProviderClient
{
    return new GithubProviderClient(new ProviderMetricFormatter);
}

function npmClient(): NpmProviderClient
{
    return new NpmProviderClient(new ProviderMetricFormatter);
}

function packagistClient(): PackagistProviderClient
{
    return new PackagistProviderClient(new ProviderMetricFormatter);
}

function dockerClient(): DockerHubProviderClient
{
    return new DockerHubProviderClient(new ProviderMetricFormatter);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('projeta as quatro metricas do github em uma resposta', function () {
    Http::fake([
        'api.github.com/*' => Http::response([
            'stargazers_count' => 1234,
            'forks_count' => 56,
            'open_issues_count' => 7,
            'license' => ['spdx_id' => 'MIT'],
            'description' => 'nunca deve ir para o payload',
        ], 200, ['ETag' => '"abc"']),
    ]);

    $payload = githubClient()->fetch(clientRequest(
        clientPair('github', 'stars', 'stars'),
        'https://api.github.com/repos/natanfiuza/devtools',
    ));

    expect($payload->metrics)->toBe([
        'stars' => 1234,
        'forks' => 56,
        'issues' => 7,
        'license' => 'MIT',
    ])->and($payload->etag)->toBe('"abc"')
        ->and($payload->notModified)->toBeFalse();

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Accept', 'application/vnd.github+json')
        && ! $request->hasHeader('Authorization'));
});

it('envia bearer apenas quando ha credencial', function () {
    Http::fake([
        'api.github.com/*' => Http::response([
            'stargazers_count' => 1,
            'forks_count' => 2,
            'open_issues_count' => 3,
            'license' => null,
        ]),
    ]);

    githubClient()->fetch(clientRequest(
        clientPair('github', 'stars', 'stars'),
        'https://api.github.com/repos/natanfiuza/devtools',
        new ProviderCredential('ghp_secret'),
    ));

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Authorization', 'Bearer ghp_secret'));
});

it('projeta versao e downloads do npm', function () {
    Http::fake([
        'registry.npmjs.org/*' => Http::response(['version' => '18.2.0']),
        'api.npmjs.org/*' => Http::response(['downloads' => 1_234_567]),
    ]);

    $version = npmClient()->fetch(clientRequest(
        clientPair('npm', 'version', 'v'),
        'https://registry.npmjs.org/vue/latest',
    ));

    $downloads = npmClient()->fetch(clientRequest(
        clientPair('npm', 'downloads', 'dm'),
        'https://api.npmjs.org/downloads/point/last-month/vue',
    ));

    expect($version->metrics)->toBe(['version' => '18.2.0'])
        ->and($downloads->metrics)->toBe(['downloads' => 1_234_567]);
});

it('projeta downloads e ultima versao do packagist', function () {
    Http::fake([
        'https://packagist.org/*' => Http::response([
            'downloads' => ['total' => 98_765, 'monthly' => 12_345, 'daily' => 678],
        ]),
        'https://repo.packagist.org/*' => Http::response([
            'packages' => [
                'laravel/framework' => [
                    ['version' => 'v11.0.0'],
                    ['version' => 'v10.0.0'],
                ],
            ],
        ]),
    ]);

    $downloads = packagistClient()->fetch(clientRequest(
        clientPair('packagist', 'downloads', 'dt'),
        'https://packagist.org/packages/laravel/framework/stats.json',
    ));

    $version = packagistClient()->fetch(clientRequest(
        clientPair('packagist', 'version', 'v'),
        'https://repo.packagist.org/p2/laravel/framework.json',
    ));

    expect($downloads->metrics)->toBe(['downloads' => 98_765])
        ->and($version->metrics)->toBe(['version' => 'v11.0.0']);
});

it('projeta pulls do docker e usa basic auth no privado', function () {
    Http::fake([
        'hub.docker.com/*' => Http::response(['pull_count' => 1_000_000_000]),
    ]);

    $payload = dockerClient()->fetch(clientRequest(
        clientPair('docker', 'pulls', 'pulls'),
        'https://hub.docker.com/v2/repositories/library/postgres/',
        new ProviderCredential('token', 'natanfiuza'),
    ));

    expect($payload->metrics)->toBe(['pulls' => 1_000_000_000]);

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader(
        'Authorization',
        'Basic '.base64_encode('natanfiuza:token'),
    ));
});

it('trata 304 como notModified com o etag corrente', function () {
    Http::fake(['api.github.com/*' => Http::response('', 304)]);

    $payload = githubClient()->fetch(clientRequest(
        clientPair('github', 'stars', 'stars'),
        'https://api.github.com/repos/natanfiuza/devtools',
        null,
        '"abc"',
    ));

    expect($payload->notModified)->toBeTrue()
        ->and($payload->metrics)->toBe([])
        ->and($payload->etag)->toBe('"abc"');
});

function fetchFailure(int $status, array $headers = []): ProviderFetchException
{
    $host = 'status-'.$status.'-'.substr(md5(json_encode($headers, JSON_THROW_ON_ERROR)), 0, 8).'.test';

    Http::fake([$host.'/*' => Http::response('', $status, $headers)]);

    try {
        githubClient()->fetch(clientRequest(clientPair('github', 'stars', 'stars'), 'https://'.$host.'/repos'));
    } catch (ProviderFetchException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a provider fetch failure.');
}

it('mapeia 500 para unavailable', function () {
    expect(fetchFailure(500)->reason)->toBe(ProviderFetchException::UNAVAILABLE);
});

it('mapeia 404 para not_found', function () {
    expect(fetchFailure(404)->reason)->toBe(ProviderFetchException::NOT_FOUND);
});

it('mapeia 429 e 403 de rate limit, com Retry-After', function () {
    $limited = fetchFailure(429, ['Retry-After' => '30']);

    expect($limited->reason)->toBe(ProviderFetchException::RATE_LIMITED)
        ->and($limited->retryAfter)->toBe(30)
        ->and(fetchFailure(403, ['X-RateLimit-Remaining' => '0'])->reason)
        ->toBe(ProviderFetchException::RATE_LIMITED);
});

it('mapeia 401 e 403 comum para unauthorized', function () {
    expect(fetchFailure(401)->reason)->toBe(ProviderFetchException::UNAUTHORIZED)
        ->and(fetchFailure(403)->reason)->toBe(ProviderFetchException::UNAUTHORIZED);
});

it('recusa JSON invalido', function () {
    Http::fake(['api.github.com/*' => Http::response('not-json')]);

    expect(fn () => githubClient()->fetch(clientRequest(
        clientPair('github', 'stars', 'stars'),
        'https://api.github.com/repos/natanfiuza/devtools',
    )))->toThrow(ProviderFetchException::class);
});

it('recusa shape inesperado', function () {
    Http::fake(['api.github.com/*' => Http::response(['stargazers_count' => 'many'])]);

    try {
        githubClient()->fetch(clientRequest(
            clientPair('github', 'stars', 'stars'),
            'https://api.github.com/repos/natanfiuza/devtools',
        ));
    } catch (ProviderFetchException $exception) {
        expect($exception->reason)->toBe(ProviderFetchException::INVALID_RESPONSE);
    }
});

it('recusa corpo maior que o limite', function () {
    config(['badgeforge.providers.max_body_bytes' => 10]);
    Http::fake(['api.github.com/*' => Http::response(str_repeat('x', 100))]);

    try {
        githubClient()->fetch(clientRequest(
            clientPair('github', 'stars', 'stars'),
            'https://api.github.com/repos/natanfiuza/devtools',
        ));
    } catch (ProviderFetchException $exception) {
        expect($exception->reason)->toBe(ProviderFetchException::INVALID_RESPONSE);
    }
});

it('distingue timeout de indisponibilidade', function () {
    Http::fake([
        'timeout.test/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
        'dns.test/*' => fn () => throw new ConnectionException('cURL error 6: Could not resolve host'),
    ]);

    try {
        githubClient()->fetch(clientRequest(clientPair('github', 'stars', 'stars'), 'https://timeout.test/repos'));
    } catch (ProviderFetchException $exception) {
        expect($exception->reason)->toBe(ProviderFetchException::TIMEOUT);
    }

    try {
        githubClient()->fetch(clientRequest(clientPair('github', 'stars', 'stars'), 'https://dns.test/repos'));
    } catch (ProviderFetchException $exception) {
        expect($exception->reason)->toBe(ProviderFetchException::UNAVAILABLE);
    }
});

it('declara suporte a privado apenas onde a API suporta', function () {
    expect(githubClient()->supportsPrivate(clientDefinition('github', 'stars', 'stars')))->toBeTrue()
        ->and(npmClient()->supportsPrivate(clientDefinition('npm', 'version', 'v')))->toBeTrue()
        ->and(npmClient()->supportsPrivate(clientDefinition('npm', 'downloads', 'dm')))->toBeFalse()
        ->and(packagistClient()->supportsPrivate(clientDefinition('packagist', 'downloads', 'dt')))->toBeFalse()
        ->and(dockerClient()->supportsPrivate(clientDefinition('docker', 'pulls', 'pulls')))->toBeTrue();
});
