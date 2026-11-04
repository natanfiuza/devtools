<?php

use App\Support\BadgeForge\Endpoint\IpAddressClassifier;
use Tests\TestCase;

uses(TestCase::class);

it('aceita apenas IP publico', function (string $ip, bool $public) {
    expect((new IpAddressClassifier)->isPublic($ip))->toBe($public);
})->with([
    ['8.8.8.8', true],
    ['1.1.1.1', true],
    ['203.0.114.1', true],
    ['2001:4860:4860::8888', true],
    ['0.0.0.0', false],
    ['10.1.2.3', false],
    ['100.64.0.1', false],
    ['127.0.0.1', false],
    ['169.254.169.254', false],
    ['172.16.0.1', false],
    ['192.168.1.1', false],
    ['192.0.2.10', false],
    ['198.18.0.1', false],
    ['198.51.100.10', false],
    ['203.0.113.10', false],
    ['224.0.0.1', false],
    ['240.0.0.1', false],
    ['::1', false],
    ['::', false],
    ['::ffff:127.0.0.1', false],
    ['::ffff:10.0.0.1', false],
    ['::192.168.1.1', false],
    ['fc00::1', false],
    ['fe80::1', false],
    ['ff02::1', false],
    ['2001:db8::1', false],
    ['64:ff9b::1', false],
    ['nao-e-ip', false],
]);
