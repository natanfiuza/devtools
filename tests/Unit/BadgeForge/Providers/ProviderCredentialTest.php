<?php

use App\Support\BadgeForge\Providers\ProviderCredential;
use Tests\TestCase;

uses(TestCase::class);

it('gera fingerprint HMAC estavel e por provider', function () {
    $credential = new ProviderCredential('ghp_secret', 'natanfiuza');

    $first = $credential->fingerprint('github');
    $second = $credential->fingerprint('github');

    expect($first)->toBe($second)
        ->and($first)->toHaveLength(64)
        ->and($first)->not->toContain('ghp_secret')
        ->and($credential->fingerprint('npm'))->not->toBe($first);
});

it('redige o token em debug e serializacao', function () {
    $credential = new ProviderCredential('ghp_secret', 'natanfiuza');

    expect($credential->__debugInfo())->toBe(['token' => '[redacted]', 'username' => 'natanfiuza'])
        ->and($credential->toArray())->toBe(['username' => 'natanfiuza'])
        ->and(json_encode($credential->toArray()))->not->toContain('ghp_secret');
});
