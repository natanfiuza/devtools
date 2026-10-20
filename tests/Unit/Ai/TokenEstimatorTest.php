<?php

use App\Support\Ai\TokenEstimator;
use Tests\TestCase;

uses(TestCase::class);

it('estima quatro caracteres por token arredondando para cima', function () {
    $estimator = new TokenEstimator;

    expect($estimator->fromCharacters(0))->toBe(1)
        ->and($estimator->fromCharacters(1))->toBe(1)
        ->and($estimator->fromCharacters(4))->toBe(1)
        ->and($estimator->fromCharacters(5))->toBe(2)
        ->and($estimator->fromCharacters(8))->toBe(2);
});

it('conta caracteres utf8 e nao bytes', function () {
    $estimator = new TokenEstimator;

    expect($estimator->inputTokens([['role' => 'user', 'content' => 'ação']]))->toBe(1)
        ->and($estimator->inputTokens([['role' => 'user', 'content' => str_repeat('é', 8)]]))->toBe(2);
});

it('soma a estimativa de saida da config ao total', function () {
    config(['ai.estimate_output_tokens' => 900]);

    $estimator = new TokenEstimator;

    expect($estimator->totalTokens([
        ['role' => 'system', 'content' => 'abcd'],
        ['role' => 'user', 'content' => 'abcde'],
    ]))->toBe(903);
});

it('aceita saida zero sem perder a estimativa de entrada', function () {
    config(['ai.estimate_output_tokens' => 0]);

    $estimator = new TokenEstimator;

    expect($estimator->totalTokens([['role' => 'user', 'content' => 'abcd']]))->toBe(1);
});
