<?php

use App\Enums\ColorCommentReportReason;
use App\Enums\ColorCommentStatus;
use App\Exceptions\Ai\InvalidContractException;
use App\Support\Colors\Moderation\ModerationContractValidator;
use Tests\TestCase;

uses(TestCase::class);

/*
| Contrato da moderacao da F7 (ADR-140).
|
| O modelo so pode devolver aprovado ou rejeitado; `flagged` e decisao da
| plataforma e campos extras invalidam a resposta inteira. O motivo tecnico da
| excecao nunca carrega o texto recebido.
*/

it('aceita aprovacao sem categoria', function () {
    $result = app(ModerationContractValidator::class)
        ->validate(['decision' => 'approved', 'category' => null]);

    expect($result->status)->toBe(ColorCommentStatus::Approved)
        ->and($result->category)->toBeNull();
});

it('aceita rejeicao com e sem categoria conhecida', function (array $data, ?ColorCommentReportReason $category) {
    $result = app(ModerationContractValidator::class)->validate($data);

    expect($result->status)->toBe(ColorCommentStatus::Rejected)
        ->and($result->category)->toBe($category);
})->with([
    [['decision' => 'rejected', 'category' => 'spam'], ColorCommentReportReason::Spam],
    [['decision' => 'rejected', 'category' => 'other'], ColorCommentReportReason::Other],
    [['decision' => 'rejected', 'category' => null], null],
]);

it('recusa decisao desconhecida, flagged e campos extras', function (array $data) {
    expect(fn () => app(ModerationContractValidator::class)->validate($data))
        ->toThrow(InvalidContractException::class);
})->with([
    'flagged' => [['decision' => 'flagged', 'category' => null]],
    'pending' => [['decision' => 'pending', 'category' => null]],
    'extra key' => [['decision' => 'approved', 'category' => null, 'reason' => 'x']],
    'missing category' => [['decision' => 'approved']],
    'category on approval' => [['decision' => 'approved', 'category' => 'spam']],
    'unknown category' => [['decision' => 'rejected', 'category' => 'whatever']],
    'decision not string' => [['decision' => 1, 'category' => null]],
    'category not string' => [['decision' => 'rejected', 'category' => 3]],
]);

it('nao copia o texto recebido para a excecao', function () {
    try {
        app(ModerationContractValidator::class)
            ->validate(['decision' => 'texto secreto do modelo', 'category' => null]);
    } catch (InvalidContractException $exception) {
        expect($exception->getMessage())->not->toContain('texto secreto do modelo');

        return;
    }

    $this->fail('A excecao esperada nao foi lancada.');
});
