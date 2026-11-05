<?php

use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderPair;
use App\Support\BadgeForge\Providers\ProviderUrlBuilder;
use Tests\TestCase;

uses(TestCase::class);

function providerPair(array $definitionOverrides = [], array $providerOverrides = []): ProviderPair
{
    $provider = new BadgeProvider(array_merge([
        'slug' => 'github',
        'name' => 'GitHub',
        'category' => 'vcs',
        'enabled' => true,
        'requires_auth' => true,
        'auth_type' => 'bearer',
    ], $providerOverrides));

    $definition = new BadgeProviderDefinition(array_merge([
        'provider_id' => 1,
        'slug' => 'stars',
        'name' => 'GitHub stars',
        'label' => 'stars',
        'client_key' => 'github',
        'extractor_key' => 'stars',
        'path_pattern' => '{owner}/{repo}',
        'required_params' => [
            ['name' => 'owner', 'pattern' => '[A-Za-z0-9-]{1,39}', 'allowSlash' => false, 'placeholderKey' => 'owner'],
            ['name' => 'repo', 'pattern' => '[A-Za-z0-9._-]{1,100}', 'allowSlash' => false, 'placeholderKey' => 'repo'],
        ],
        'url_template' => 'https://api.github.com/repos/{owner}/{repo}',
        'supports_private' => true,
        'example_path' => 'natanfiuza/devtools',
        'enabled' => true,
    ], $definitionOverrides));

    return new ProviderPair($provider, $definition);
}

it('substitui placeholders com rawurlencode', function () {
    $url = (new ProviderUrlBuilder)->build(providerPair(), [
        'owner' => 'natan fiuza',
        'repo' => 'dev/tools',
    ]);

    expect($url)->toBe('https://api.github.com/repos/natan%20fiuza/dev%2Ftools');
});

it('preserva barra entre segmentos quando allowSlash', function () {
    $pair = providerPair([
        'required_params' => [
            ['name' => 'package', 'pattern' => '.+', 'allowSlash' => true, 'placeholderKey' => 'package'],
        ],
        'url_template' => 'https://registry.npmjs.org/{package}/latest',
    ]);

    expect((new ProviderUrlBuilder)->build($pair, ['package' => '@scope/pkg']))
        ->toBe('https://registry.npmjs.org/@scope/pkg/latest');
});

it('devolve o template literal quando a definicao nao tem params', function () {
    $pair = providerPair([
        'path_pattern' => 'latest',
        'required_params' => [],
        'url_template' => 'https://packagist.org/packages/laravel/framework/stats.json',
        'example_path' => 'latest',
    ]);

    expect((new ProviderUrlBuilder)->build($pair, []))
        ->toBe('https://packagist.org/packages/laravel/framework/stats.json');
});

it('recusa template http, com userinfo ou com porta', function () {
    expect(fn () => (new ProviderUrlBuilder)->build(
        providerPair(['url_template' => 'http://api.github.com/repos/{owner}/{repo}']),
        ['owner' => 'a', 'repo' => 'b'],
    ))->toThrow(ProviderFetchException::class);

    expect(fn () => (new ProviderUrlBuilder)->build(
        providerPair(['url_template' => 'https://user:pass@api.github.com/repos/{owner}/{repo}']),
        ['owner' => 'a', 'repo' => 'b'],
    ))->toThrow(ProviderFetchException::class);

    expect(fn () => (new ProviderUrlBuilder)->build(
        providerPair(['url_template' => 'https://api.github.com:8443/repos/{owner}/{repo}']),
        ['owner' => 'a', 'repo' => 'b'],
    ))->toThrow(ProviderFetchException::class);
});

it('recusa placeholder que muda o host e placeholder desconhecido', function () {
    expect(fn () => (new ProviderUrlBuilder)->build(
        providerPair([
            'required_params' => [
                ['name' => 'owner', 'pattern' => '.+', 'allowSlash' => false, 'placeholderKey' => 'owner'],
            ],
            'url_template' => 'https://{owner}/repos',
        ]),
        ['owner' => 'evil.example'],
    ))->toThrow(ProviderFetchException::class);

    $pair = providerPair(['url_template' => 'https://api.github.com/repos/{owner}/{missing}']);

    expect(fn () => (new ProviderUrlBuilder)->build($pair, ['owner' => 'a', 'repo' => 'b']))
        ->toThrow(ProviderFetchException::class);
});

it('recusa param ausente ou vazio', function () {
    expect(fn () => (new ProviderUrlBuilder)->build(providerPair(), ['owner' => 'a']))
        ->toThrow(ProviderFetchException::class);

    expect(fn () => (new ProviderUrlBuilder)->build(providerPair(), ['owner' => 'a', 'repo' => '']))
        ->toThrow(ProviderFetchException::class);
});
