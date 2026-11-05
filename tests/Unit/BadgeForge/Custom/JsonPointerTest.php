<?php

use App\Support\BadgeForge\Custom\JsonPointer;
use Tests\TestCase;

uses(TestCase::class);

it('extrai escalares de objeto, lista e tokens escapados', function () {
    $document = [
        'repo' => [
            'stars' => 42,
            'license' => ['spdx' => 'MIT'],
            'tags' => ['v1', 'v2'],
        ],
        'a/b' => 'slash',
        'a~b' => 'tilde',
        'ratio' => 66.89,
        'active' => true,
    ];

    expect(JsonPointer::extract($document, '/repo/stars'))->toBe(42)
        ->and(JsonPointer::extract($document, '/repo/license/spdx'))->toBe('MIT')
        ->and(JsonPointer::extract($document, '/repo/tags/1'))->toBe('v2')
        ->and(JsonPointer::extract($document, '/a~1b'))->toBe('slash')
        ->and(JsonPointer::extract($document, '/a~0b'))->toBe('tilde')
        ->and(JsonPointer::extract($document, '/ratio'))->toBe(66.89)
        ->and(JsonPointer::extract($document, '/active'))->toBeTrue()
        ->and(JsonPointer::extract($document, ''))->toBeNull();
});

it('devolve null para ausente, nao escalar ou indice invalido', function () {
    $document = ['obj' => ['x' => 1], 'list' => ['a'], 'nil' => null];

    expect(JsonPointer::extract($document, '/missing'))->toBeNull()
        ->and(JsonPointer::extract($document, '/obj'))->toBeNull()
        ->and(JsonPointer::extract($document, '/list'))->toBeNull()
        ->and(JsonPointer::extract($document, '/nil'))->toBeNull()
        ->and(JsonPointer::extract($document, '/list/01'))->toBeNull()
        ->and(JsonPointer::extract($document, '/list/5'))->toBeNull()
        ->and(JsonPointer::extract($document, '/obj/x/y'))->toBeNull();
});

it('rejeita sintaxe de pointer invalida', function (string $pointer) {
    expect(JsonPointer::isValid($pointer))->toBeFalse()
        ->and(JsonPointer::extract(['a' => 1], $pointer))->toBeNull();
})->with([
    'sem barra' => ['repo/stars'],
    'escape invalido' => ['/a~2b'],
    'tilde no fim' => ['/a~'],
]);

it('respeita o limite de tamanho do pointer', function () {
    config()->set('badgeforge.custom_providers.pointer_max_length', 8);

    expect(JsonPointer::isValid('/1234567'))->toBeTrue()
        ->and(JsonPointer::isValid('/12345678'))->toBeFalse();
});
