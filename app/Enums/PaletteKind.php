<?php

namespace App\Enums;

/**
 * Tipo de paleta persistida (F4, ADR-123).
 *
 * `Scale` guarda a base e os 11 tons efetivos da escala deterministica;
 * `Extracted` guarda a base escolhida e as 5/7/9/11 cores dominantes da
 * imagem. Em ambos os casos as cores sao persistidas, nunca recalculadas ao
 * abrir. O valor e interpretado junto de `algorithm_version`.
 */
enum PaletteKind: string
{
    case Scale = 'scale';
    case Extracted = 'extracted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }

    public function labelKey(): string
    {
        return 'colors.kinds.'.$this->value;
    }
}
