<?php

namespace App\Enums;

/**
 * Por que o provedor recusou o Pix (ADR-059).
 *
 * A tela do doador recebe o motivo **ja traduzido**: o `status_detail` cru
 * fica no banco, vai para o admin e para o comando `donations:check`, mas nunca
 * para a prop publica. Codigo desconhecido (ou ausente) cai em `Other` - melhor
 * uma explicacao generica do que esconder que a doacao falhou.
 */
enum DonationFailureReason: string
{
    case HighRisk = 'high_risk';
    case Bank = 'bank';
    case Regulations = 'regulations';
    case InsufficientData = 'insufficient_data';
    case BizRule = 'biz_rule';
    case Other = 'other';

    public static function fromStatusDetail(?string $detail): self
    {
        return match ($detail) {
            'rejected_high_risk' => self::HighRisk,
            'rejected_by_bank', 'bank_error' => self::Bank,
            'rejected_by_regulations' => self::Regulations,
            'rejected_insufficient_data' => self::InsufficientData,
            'rejected_by_biz_rule' => self::BizRule,
            default => self::Other,
        };
    }

    public function translationKey(): string
    {
        return 'donations.reasons.'.$this->value;
    }
}
