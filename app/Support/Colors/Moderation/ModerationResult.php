<?php

namespace App\Support\Colors\Moderation;

use App\Enums\ColorCommentReportReason;
use App\Enums\ColorCommentStatus;

/**
 * Veredito valido de uma moderacao (F7, ADR-140).
 *
 * So existem duas decisoes: aprovado ou rejeitado. `flagged` nunca vem do
 * modelo - e o estado de falha/reserva para o admin. A categoria so existe em
 * rejeicao e reusa os motivos da denuncia para o filtro administrativo.
 */
final readonly class ModerationResult
{
    public function __construct(
        public ColorCommentStatus $status,
        public ?ColorCommentReportReason $category = null,
    ) {
        if ($status !== ColorCommentStatus::Approved && $status !== ColorCommentStatus::Rejected) {
            throw new \InvalidArgumentException('A moderation result must be approved or rejected.');
        }

        if ($status === ColorCommentStatus::Approved && $category !== null) {
            throw new \InvalidArgumentException('An approved comment cannot carry a category.');
        }
    }

    /**
     * @return array{decision: string, category: string|null}
     */
    public function toArray(): array
    {
        return [
            'decision' => $this->status->value,
            'category' => $this->category?->value,
        ];
    }

    public function json(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
