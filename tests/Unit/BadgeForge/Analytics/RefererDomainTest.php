<?php

use App\Support\BadgeForge\Analytics\RefererDomain;
use Tests\TestCase;

uses(TestCase::class);

it('guarda apenas o host em minusculas', function (?string $header, ?string $expected) {
    expect(RefererDomain::from($header))->toBe($expected);
})->with([
    'ausente' => [null, null],
    'vazio' => ['   ', null],
    'https com path e query' => ['https://Example.COM/readme?tab=1', 'example.com'],
    'subdominio' => ['https://Gist.GitHub.com/x', 'gist.github.com'],
    'porta' => ['https://example.com:8443/x', 'example.com'],
    'credenciais' => ['https://user:pass@example.com/x', 'example.com'],
    'sem esquema' => ['example.com/readme', null],
    'host com ponto final' => ['https://example.com./x', 'example.com'],
    'host longo' => ['https://'.str_repeat('a', 254).'.com/x', null],
]);
