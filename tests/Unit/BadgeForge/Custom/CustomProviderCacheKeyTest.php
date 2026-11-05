<?php

use App\Support\BadgeForge\Custom\CachedCustomMetrics;
use App\Support\BadgeForge\Custom\CustomProviderCacheKey;
use Tests\TestCase;

uses(TestCase::class);

it('inclui ULID, versao e hash da URL sem expor a origem', function () {
    $url = 'https://api.example.com/repos/a/b';
    $key = CustomProviderCacheKey::for('01JABCDEFGHJKMNPQRSTVWXYZ', 3, $url);

    expect($key)->toStartWith('badgeforge:custom:v1:01JABCDEFGHJKMNPQRSTVWXYZ:3:')
        ->and($key)->not->toContain('example.com')
        ->and($key)->not->toContain('/repos/')
        ->and($key)->toEndWith(hash('sha256', $url));
});

it('versao diferente gera chave diferente', function () {
    $url = 'https://api.example.com/x';

    expect(CustomProviderCacheKey::for('01JABCDEFGHJKMNPQRSTVWXYZ', 1, $url))
        ->not->toBe(CustomProviderCacheKey::for('01JABCDEFGHJKMNPQRSTVWXYZ', 2, $url));
});

it('faz roundtrip da entrada de cache e recusa shape invalido', function () {
    $now = time();
    $entry = CachedCustomMetrics::fromMetrics(['stars' => '1.2k', 'vazio' => null], '"etag"', $now, 3600);

    expect(CachedCustomMetrics::fromArray($entry->toArray()))->toEqual($entry)
        ->and(CachedCustomMetrics::fromArray(['metrics' => 'x']))->toBeNull()
        ->and(CachedCustomMetrics::fromArray(['metrics' => ['a' => 1], 'fetched_at' => 1, 'stale_until' => 2]))->toBeNull();
});
