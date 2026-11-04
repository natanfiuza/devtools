<?php

use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderCacheKey;
use App\Support\BadgeForge\Providers\ProviderCredential;
use App\Support\BadgeForge\Providers\ProviderPair;
use Tests\TestCase;

uses(TestCase::class);

function cacheKeyPair(string $providerSlug = 'github', string $definitionSlug = 'stars'): ProviderPair
{
    return new ProviderPair(
        new BadgeProvider(['slug' => $providerSlug]),
        new BadgeProviderDefinition(['slug' => $definitionSlug, 'extractor_key' => 'stars']),
    );
}

it('e estavel e prefixada pelo namespace do provider', function () {
    $key = ProviderCacheKey::for(cacheKeyPair(), 'https://api.github.com/repos/a/b', null);

    expect($key)->toStartWith('badgeforge:provider:')
        ->and($key)->toBe(ProviderCacheKey::for(cacheKeyPair(), 'https://api.github.com/repos/a/b', null))
        ->and(strlen(substr($key, strlen('badgeforge:provider:'))))->toBe(64);
});

it('separa por URL resolvida e por provider', function () {
    $pair = cacheKeyPair();

    expect(ProviderCacheKey::for($pair, 'https://api.github.com/repos/a/b', null))
        ->not->toBe(ProviderCacheKey::for($pair, 'https://api.github.com/repos/a/c', null))
        ->and(ProviderCacheKey::for($pair, 'https://api.github.com/repos/a/b', null))
        ->not->toBe(ProviderCacheKey::for(cacheKeyPair('npm'), 'https://api.github.com/repos/a/b', null));
});

it('compartilha a entrada entre as definicoes do GitHub pela URL', function () {
    $url = 'https://api.github.com/repos/natanfiuza/devtools';

    $keys = array_map(
        static fn (string $definition): string => ProviderCacheKey::for(cacheKeyPair('github', $definition), $url, null),
        ['stars', 'forks', 'issues', 'license'],
    );

    expect(array_unique($keys))->toHaveCount(1);
});

it('separa publico de privado pelo fingerprint sem guardar o token', function () {
    $pair = cacheKeyPair();
    $url = 'https://api.github.com/repos/a/b';
    $credential = new ProviderCredential('ghp_token_secreto');

    $public = ProviderCacheKey::for($pair, $url, null);
    $private = ProviderCacheKey::for($pair, $url, $credential);

    expect($private)->not->toBe($public)
        ->and($private)->not->toContain('ghp_token_secreto')
        ->and($private)->not->toContain($credential->fingerprint('github'));
});

it('considera o username do Docker na identidade privada', function () {
    $pair = cacheKeyPair('docker', 'pulls');
    $url = 'https://hub.docker.com/v2/repositories/a/b/';

    expect(ProviderCacheKey::for($pair, $url, new ProviderCredential('tok', 'alice')))
        ->not->toBe(ProviderCacheKey::for($pair, $url, new ProviderCredential('tok', 'bob')));
});
