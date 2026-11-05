<?php

use App\Support\BadgeForge\Analytics\MaxMindCountryReader;
use App\Support\BadgeForge\Analytics\NullCountryReader;
use Tests\TestCase;

uses(TestCase::class);

it('devolve null quando o arquivo GeoIP nao existe', function () {
    $reader = new MaxMindCountryReader(storage_path('framework/testing/geoip-ausente.mmdb'));

    expect($reader->country('203.0.113.10'))->toBeNull();
});

it('devolve null para IP ausente, invalido ou privado', function () {
    $reader = new MaxMindCountryReader(storage_path('framework/testing/geoip-ausente.mmdb'));

    expect($reader->country(null))->toBeNull()
        ->and($reader->country('nao-e-ip'))->toBeNull()
        ->and($reader->country('10.0.0.1'))->toBeNull()
        ->and($reader->country('127.0.0.1'))->toBeNull()
        ->and($reader->country('192.168.0.1'))->toBeNull();
});

it('o leitor nulo sempre devolve null', function () {
    expect((new NullCountryReader)->country('203.0.113.10'))->toBeNull();
});
