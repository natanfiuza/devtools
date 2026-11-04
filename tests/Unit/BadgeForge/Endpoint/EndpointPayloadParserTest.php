<?php

namespace Tests\Unit\BadgeForge\Endpoint;

use App\Support\BadgeForge\Endpoint\EndpointFetchException;
use App\Support\BadgeForge\Endpoint\EndpointFetchReason;
use App\Support\BadgeForge\Endpoint\EndpointPayload;
use App\Support\BadgeForge\Endpoint\EndpointPayloadParser;
use Tests\TestCase;

uses(TestCase::class);

function endpointTestValidPayload(array $overrides = []): string
{
    return json_encode(array_merge([
        'schemaVersion' => 1,
        'label' => 'build',
        'message' => 'passing',
    ], $overrides), JSON_THROW_ON_ERROR);
}

it('aceita o nucleo shields v1 e ignora os campos cosmeticos', function () {
    $payload = (new EndpointPayloadParser)->parse(endpointTestValidPayload([
        'color' => 'brightgreen',
        'labelColor' => 'black',
        'logoSvg' => '<svg></svg>',
        'namedLogo' => 'github',
        'style' => 'for-the-badge',
        'cacheSeconds' => 600,
        'link' => 'https://example.com',
    ]));

    expect($payload)->toBeInstanceOf(EndpointPayload::class)
        ->and($payload->label)->toBe('build')
        ->and($payload->message)->toBe('passing')
        ->and($payload->color)->toBe('brightgreen')
        ->and($payload->labelColor)->toBe('black');
});

it('devolve nulo para cores ausentes ou vazias', function () {
    $payload = (new EndpointPayloadParser)->parse(endpointTestValidPayload([
        'color' => '   ',
        'labelColor' => 42,
    ]));

    expect($payload->color)->toBeNull()
        ->and($payload->labelColor)->toBeNull();
});

it('recusa payloads fora do contrato', function (string $body) {
    try {
        (new EndpointPayloadParser)->parse($body);
        $this->fail('O payload deveria ter sido recusado.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::InvalidResponse);
    }
})->with([
    'json invalido' => ['{not json'],
    'corpo vazio' => [''],
    'lista' => ['[{"schemaVersion":1,"label":"a","message":"b"}]'],
    'escalar' => ['"shields"'],
    'sem schema' => ['{"label":"build","message":"passing"}'],
    'schema string' => ['{"schemaVersion":"1","label":"build","message":"passing"}'],
    'schema futuro' => ['{"schemaVersion":2,"label":"build","message":"passing"}'],
    'sem label' => ['{"schemaVersion":1,"message":"passing"}'],
    'label vazia' => ['{"schemaVersion":1,"label":" ","message":"passing"}'],
    'label nao string' => ['{"schemaVersion":1,"label":1,"message":"passing"}'],
    'sem message' => ['{"schemaVersion":1,"label":"build"}'],
    'message vazia' => ['{"schemaVersion":1,"label":"build","message":""}'],
    'message nao string' => ['{"schemaVersion":1,"label":"build","message":[]}'],
]);

it('respeita o limite de profundidade configurado', function () {
    config(['badgeforge.endpoint.max_json_depth' => 2]);

    $nested = '{"schemaVersion":1,"label":"build","message":"passing","extra":'.str_repeat('[', 8)
        .str_repeat(']', 8).'}';

    try {
        (new EndpointPayloadParser)->parse($nested);
        $this->fail('O payload profundo deveria ter sido recusado.');
    } catch (EndpointFetchException $exception) {
        expect($exception->reason)->toBe(EndpointFetchReason::InvalidResponse);
    }
});
