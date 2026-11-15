<?php

namespace App\Enums;

/**
 * Motivo de uma denuncia de comentario (F7, ADR-141).
 *
 * Lista fechada e sem texto livre: o denunciante escolhe um valor e o admin
 * filtra pelo mesmo conjunto. Texto livre viraria mais um campo de conteudo
 * sem moderacao.
 */
enum ColorCommentReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Hate = 'hate';
    case Sexual = 'sexual';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::cases());
    }

    public function labelKey(): string
    {
        return 'colors.reports.reasons.'.$this->value;
    }
}
