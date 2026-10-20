<?php

use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Exceptions\Ai\PayloadCodecException;
use App\Support\Ai\CanonicalPayload;
use App\Support\Ai\PayloadCodec;

it('faz ida e volta do payload canonico', function () {
    $payload = new CanonicalPayload(
        SchemaSource::Ddl,
        SqlDialect::Mysql,
        'sql',
        "CREATE TABLE users (\n  id int\n)",
        'Liste os usuarios',
        'model-a',
        1,
    );

    $codec = new PayloadCodec;
    $decoded = $codec->decode($codec->encode($payload));

    expect($decoded->toArray())->toBe($payload->toArray())
        ->and($decoded->hash())->toBe($payload->hash());
});

it('recusa base64 ou fluxo comprimido invalido', function () {
    $codec = new PayloadCodec;

    expect(fn () => $codec->decode('nao-e-base64'))
        ->toThrow(PayloadCodecException::class);

    expect(fn () => $codec->decode(base64_encode('texto sem gzip')))
        ->toThrow(PayloadCodecException::class);

    expect(fn () => $codec->decode(''))
        ->toThrow(PayloadCodecException::class);
});

it('recusa json comprimido que nao tem a forma do payload', function () {
    $codec = new PayloadCodec;
    $compressed = gzcompress(json_encode(['schema' => 'sem os outros campos'], JSON_THROW_ON_ERROR), 6);

    expect($compressed)->toBeString();

    expect(fn () => $codec->decode(base64_encode($compressed === false ? '' : $compressed)))
        ->toThrow(PayloadCodecException::class);
});
