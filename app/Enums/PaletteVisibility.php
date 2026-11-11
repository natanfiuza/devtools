<?php

namespace App\Enums;

/**
 * Visibilidade de uma paleta (F4, ADR-124).
 *
 * `Private` e o padrao de toda paleta criada ou duplicada; `Public` exige
 * slug e coloca a paleta no sitemap. A visibilidade nao muda o slug: uma
 * paleta que volta a ser privada mantem o slug reservado para si.
 */
enum PaletteVisibility: string
{
    case Private = 'private';
    case Public = 'public';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $visibility): string => $visibility->value, self::cases());
    }

    public function labelKey(): string
    {
        return 'colors.visibility.'.$this->value;
    }
}
