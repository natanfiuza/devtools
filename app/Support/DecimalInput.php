<?php

namespace App\Support;

/**
 * Valor de dinheiro digitado pelo admin, na moeda dele.
 */
final class DecimalInput
{
    /**
     * Deixa o valor em ponto decimal.
     *
     * O separador decimal e o ultimo sinal que aparece no numero: `29,90` e
     * `29.90` viram os dois `29.90`, e `1.234,56` vira `1234.56`. Quem escreve
     * com separador de milhar digita os dois, como em qualquer calculadora -
     * `2.990` sozinho continua sendo lido como o numero que esta escrito.
     *
     * O que nao der para ler assim segue como veio, e a validacao recusa: valor
     * de dinheiro nao se adivinha.
     */
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');

        if ($lastDot === false && $lastComma === false) {
            return $value;
        }

        $decimal = $lastDot > $lastComma ? '.' : ',';
        $thousands = $decimal === '.' ? ',' : '.';

        // Primeiro o milhar sai, depois o separador decimal vira ponto: na
        // ordem inversa o ponto recem-criado seria lido como milhar.
        return str_replace($decimal, '.', str_replace($thousands, '', $value));
    }

    /**
     * Valor ja validado (numerico) em centavos.
     */
    public static function toCents(mixed $value): int
    {
        return is_numeric($value) ? (int) round(((float) $value) * 100) : 0;
    }
}
