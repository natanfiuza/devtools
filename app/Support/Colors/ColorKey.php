<?php

namespace App\Support\Colors;

/**
 * Chave canonica de cor da biblioteca pessoal (F5, ADR-127).
 *
 * O picker grava `#rrggbbaa` em minusculas ou `transparent`. O servidor
 * normaliza a entrada e so aceita essa forma: a unicidade por conta depende
 * de uma representacao unica, e formatos como `#rrggbb` ou nomes de cor
 * perderiam alpha ou ambiguidade.
 */
final class ColorKey
{
    public const PATTERN = '/^(#[0-9a-f]{8}|transparent)$/';

    /**
     * Normaliza caixa e espacos antes da validacao. Formatos invalidos
     * continuam invalidos e sao recusados pela regra de `regex`.
     */
    public static function normalize(string $color): string
    {
        return strtolower(trim($color));
    }

    public static function isCanonical(string $color): bool
    {
        return preg_match(self::PATTERN, $color) === 1;
    }
}
