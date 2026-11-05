<?php

use App\Support\BadgeForge\Analytics\PrivacyHash;
use Tests\TestCase;

uses(TestCase::class);

it('gera HMAC-SHA256 deterministico com a APP_KEY', function () {
    $first = PrivacyHash::of('203.0.113.10');

    expect($first)->toBe(hash_hmac('sha256', '203.0.113.10', (string) config('app.key')))
        ->and($first)->toHaveLength(64)
        ->and(PrivacyHash::of('203.0.113.10'))->toBe($first)
        ->and(PrivacyHash::of('203.0.113.11'))->not->toBe($first);
});

it('trata ausente como string vazia', function () {
    expect(PrivacyHash::of(null))->toBe(PrivacyHash::of(''));
});

it('muda quando a chave muda', function () {
    $original = PrivacyHash::of('203.0.113.10');

    config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);

    expect(PrivacyHash::of('203.0.113.10'))->not->toBe($original);
});
