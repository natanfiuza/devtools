<?php

use App\Enums\DonationFailureReason;

/*
| Motivo de recusa da doacao (ADR-059).
|
| O `status_detail` do provedor vira uma chave de traducao. Nenhum codigo cru
| chega ao doador; o que nao estiver no mapa cai no motivo generico.
*/

it('traduz os detalhes conhecidos', function (string $detail, DonationFailureReason $reason) {
    expect(DonationFailureReason::fromStatusDetail($detail))->toBe($reason)
        ->and($reason->translationKey())->toBe('donations.reasons.'.$reason->value);
})->with([
    'analise de risco' => ['rejected_high_risk', DonationFailureReason::HighRisk],
    'recusa do banco' => ['rejected_by_bank', DonationFailureReason::Bank],
    'erro do banco' => ['bank_error', DonationFailureReason::Bank],
    'regras regulatorias' => ['rejected_by_regulations', DonationFailureReason::Regulations],
    'dados insuficientes' => ['rejected_insufficient_data', DonationFailureReason::InsufficientData],
    'regra de negocio' => ['rejected_by_biz_rule', DonationFailureReason::BizRule],
]);

it('cai no motivo generico para codigo desconhecido ou ausente', function (?string $detail) {
    expect(DonationFailureReason::fromStatusDetail($detail))->toBe(DonationFailureReason::Other)
        ->and(DonationFailureReason::Other->translationKey())->toBe('donations.reasons.other');
})->with([
    'desconhecido' => ['algo_que_o_provedor_inventou'],
    'ausente' => [null],
]);
