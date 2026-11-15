<?php

namespace App\Enums;

/**
 * Estado de moderacao de um comentario (F7, ADR-139/ADR-140).
 *
 * `Pending` e `Flagged` sao visiveis ao publico apenas com o selo "em analise";
 * `Approved` e o unico estado apresentado como definitivo; `Rejected` so mostra
 * o `approved_body` anterior, quando existir. Falha definitiva da IA nunca
 * aprova: vira `Flagged` para o admin decidir.
 */
enum ColorCommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Flagged = 'flagged';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function labelKey(): string
    {
        return 'colors.comments.status.'.$this->value;
    }

    /**
     * Estados que aparecem ao publico com o selo "em analise".
     */
    public function awaitsReview(): bool
    {
        return $this === self::Pending || $this === self::Flagged;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }
}
