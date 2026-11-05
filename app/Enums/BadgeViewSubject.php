<?php

namespace App\Enums;

/**
 * Sujeito de uma visualizacao de imagem com dono (F7, ADR-086).
 *
 * Estatica e provider global ficam de fora de proposito: nao tem dono para
 * consultar. O valor e persistido em `badge_views.subject_type` e
 * `badge_view_dailies.subject_type`.
 */
enum BadgeViewSubject: string
{
    case Personal = 'personal';
    case Endpoint = 'endpoint';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $subject): string => $subject->value, self::cases());
    }

    /**
     * Chave i18n do nome do sujeito nas telas de analytics e CSV.
     */
    public function labelKey(): string
    {
        return 'account.badgeforge.analytics.subjects.'.$this->value;
    }
}
