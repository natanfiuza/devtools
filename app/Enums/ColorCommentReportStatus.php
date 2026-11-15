<?php

namespace App\Enums;

/**
 * Estado de uma denuncia de comentario (F7, ADR-141).
 *
 * `Pending` aguarda o admin; `Resolved` aceita a denuncia (normalmente junto
 * com rejeitar/excluir o comentario); `Dismissed` a dispensa. Uma denuncia
 * dispensada pode ser reaberta pelo mesmo denunciante.
 */
enum ColorCommentReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function labelKey(): string
    {
        return 'colors.reports.status.'.$this->value;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
