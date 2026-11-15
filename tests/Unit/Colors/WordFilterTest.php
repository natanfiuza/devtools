<?php

use App\Support\Colors\Moderation\WordFilter;
use Tests\TestCase;

uses(TestCase::class);

/*
| Filtro local da F7 (ADR-140).
|
| Compara tokens/ngramas depois de normalizar; nunca substring. A lista
| inglesa e sempre mesclada, e um diretorio ausente nao derruba a requisicao.
*/

beforeEach(function () {
    config()->set(
        'colors.comments.moderation.word_lists_path',
        resource_path('colors/moderation'),
    );
});

it('bloqueia termo exato com maiusculas e pontuacao', function () {
    $filter = app(WordFilter::class);

    expect($filter->matches('Isso é MERDA!!!', 'pt_BR'))->toBeTrue()
        ->and($filter->matches('foda-se mesmo', 'pt_BR'))->toBeTrue()
        ->and($filter->matches('cabrón', 'es'))->toBeTrue();
});

it('bloqueia expressoes por sequencia de tokens', function () {
    $filter = app(WordFilter::class);

    expect($filter->matches('eu digo: vai se foder', 'pt_BR'))->toBeTrue()
        ->and($filter->matches('just kill yourself', 'en'))->toBeTrue();
});

it('remove zero-width antes de comparar', function () {
    $filter = app(WordFilter::class);

    expect($filter->matches("me\u{200B}rda", 'pt_BR'))->toBeTrue();
});

it('nao bloqueia substring de palavra comum', function () {
    $filter = app(WordFilter::class);

    expect($filter->matches('A classy bass assumption', 'en'))->toBeFalse()
        ->and($filter->matches('A aula clássica foi ótima', 'pt_BR'))->toBeFalse();
});

it('mescla a lista inglesa em qualquer idioma', function () {
    $filter = app(WordFilter::class);

    expect($filter->matches('that is shit', 'pt_BR'))->toBeTrue();
});

it('nao quebra quando a lista nao existe', function () {
    config()->set(
        'colors.comments.moderation.word_lists_path',
        resource_path('colors/does-not-exist'),
    );

    expect(app(WordFilter::class)->matches('merda', 'pt_BR'))->toBeFalse();
});
