<?php

use App\Support\BadgeForge\Custom\CustomPathParams;
use Tests\TestCase;

uses(TestCase::class);

it('devolve um mapa nome => segmento na ordem declarada', function () {
    expect(CustomPathParams::parse('natanfiuza/devtools', ['owner', 'repo']))
        ->toBe(['owner' => 'natanfiuza', 'repo' => 'devtools']);

    expect(CustomPathParams::parse('1.0/v1_0~x', ['version', 'tag']))
        ->toBe(['version' => '1.0', 'tag' => 'v1_0~x']);
});

it('recusa contagem diferente, traversal, vazio e caractere inseguro', function (string $path) {
    expect(CustomPathParams::parse($path, ['owner', 'repo']))->toBeNull();
})->with([
    'vazio' => [''],
    'um segmento' => ['owner'],
    'tres segmentos' => ['a/b/c'],
    'barra inicial' => ['/a/b'],
    'barra final' => ['a/b/'],
    'ponto' => ['a/.'],
    'ponto duplo' => ['a/..'],
    'segmento vazio' => ['a//b'],
    'espaco' => ['a/b c'],
    'controle' => ["a/b\x01"],
    'barra codificada' => ['a%2Fb/c'],
]);

it('recusa segmento acima do limite configurado', function () {
    config()->set('badgeforge.custom_providers.param_max_length', 3);

    expect(CustomPathParams::parse('abc/def', ['a', 'b']))->not->toBeNull()
        ->and(CustomPathParams::parse('abcd/def', ['a', 'b']))->toBeNull();
});
