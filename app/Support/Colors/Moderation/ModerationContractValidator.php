<?php

namespace App\Support\Colors\Moderation;

use App\Enums\ColorCommentReportReason;
use App\Enums\ColorCommentStatus;
use App\Exceptions\Ai\InvalidContractException;

/**
 * Contrato estreito da moderacao (F7, ADR-140).
 *
 * Aceita exatamente `decision` e `category`. Campos extras, `flagged`, texto
 * livre ou valores fora do enum viram `invalid_contract`; o job transforma
 * isso em `flagged`, nunca em aprovacao. Nada do JSON recebido entra na
 * excecao.
 */
final class ModerationContractValidator
{
    /**
     * @param  array<mixed>  $data
     */
    public function validate(array $data): ModerationResult
    {
        if (count($data) !== 2
            || ! array_key_exists('decision', $data)
            || ! array_key_exists('category', $data)) {
            throw InvalidContractException::because('moderation_keys');
        }

        $decision = $data['decision'];

        if (! is_string($decision)) {
            throw InvalidContractException::because('moderation_decision_not_string');
        }

        $status = match ($decision) {
            ColorCommentStatus::Approved->value => ColorCommentStatus::Approved,
            ColorCommentStatus::Rejected->value => ColorCommentStatus::Rejected,
            default => throw InvalidContractException::because('moderation_decision_invalid'),
        };

        $rawCategory = $data['category'];

        if ($rawCategory === null) {
            return new ModerationResult($status);
        }

        if (! is_string($rawCategory)) {
            throw InvalidContractException::because('moderation_category_not_string');
        }

        if ($status === ColorCommentStatus::Approved) {
            throw InvalidContractException::because('moderation_category_on_approval');
        }

        $category = ColorCommentReportReason::tryFrom($rawCategory);

        if ($category === null) {
            throw InvalidContractException::because('moderation_category_invalid');
        }

        return new ModerationResult($status, $category);
    }
}
