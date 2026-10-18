<?php

namespace App\Support;

use Carbon\CarbonInterface;
use NumberFormatter;

/**
 * Formata numero, dinheiro e data para exibicao.
 *
 * A formatacao acontece no servidor de proposito: o texto sai pronto no HTML
 * do SSR e o cliente hidrata exatamente a mesma string (o `Intl` do navegador
 * formataria com o fuso e o idioma da maquina, e o horario de verao de quem
 * esta longe apareceria diferente do que o servidor escreveu).
 *
 * O idioma e o da requisicao, e nao o da conta: e o idioma em que a tela esta
 * (ADR-026).
 */
final class Format
{
    public static function date(?CarbonInterface $value): ?string
    {
        return self::format($value, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);
    }

    public static function dateTime(?CarbonInterface $value): ?string
    {
        return self::format($value, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    }

    /**
     * Numero inteiro com o separador de milhar do idioma.
     */
    public static function number(int $value): string
    {
        $formatter = new NumberFormatter(self::locale(), NumberFormatter::DECIMAL);

        return $formatter->format($value) ?: (string) $value;
    }

    /**
     * Valor em centavos como moeda. A moeda vem do dado (plano ou pagamento) e
     * nunca de uma constante do codigo.
     */
    public static function money(int $cents, string $currency): string
    {
        $formatter = new NumberFormatter(self::locale(), NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($cents / 100, $currency)
            ?: $currency.' '.number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Tamanho em bytes na unidade que se le de relance: 8192 vira "8 KB".
     *
     * As siglas (B, KB, MB, GB) sao iguais nos 5 idiomas - sao simbolo de
     * unidade, como o codigo da moeda em `money()` -, mas o numero sai no
     * formato do idioma (virgula decimal em pt/fr/es).
     */
    public static function bytes(int $bytes): string
    {
        foreach (['GB' => 1024 ** 3, 'MB' => 1024 ** 2, 'KB' => 1024] as $unit => $step) {
            if ($bytes >= $step) {
                return self::decimal($bytes / $step).' '.$unit;
            }
        }

        return self::number($bytes).' B';
    }

    /**
     * Numero com no maximo uma casa decimal: 8,0 vira "8" e 1,5 continua "1,5".
     */
    private static function decimal(float $value): string
    {
        $formatter = new NumberFormatter(self::locale(), NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);

        return $formatter->format($value) ?: (string) round($value, 1);
    }

    /**
     * Tamanho do texto de uma data no idioma da tela.
     */
    private static function format(
        ?CarbonInterface $value,
        int $dateType,
        int $timeType,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $formatter = new \IntlDateFormatter(
            self::locale(),
            $dateType,
            $timeType,
            self::timezone($value),
        );

        return $formatter->format($value->getTimestamp()) ?: $value->toDateTimeString();
    }

    /**
     * Fuso horario compativel com o ICU/IntlDateFormatter.
     *
     * O IntlDateFormatter nao aceita deslocamentos crus como "+00:00" ou "-03:00"
     * (dispara IntlException com U_ILLEGAL_ARGUMENT_ERROR). ICU exige identificadores
     * IANA (UTC, America/Recife) ou deslocamentos prefixados por GMT ("GMT+00:00").
     */
    private static function timezone(CarbonInterface $value): string
    {
        $name = $value->getTimezone()->getName();

        if ($name === 'Z' || $name === '+00:00' || $name === '-00:00' || $name === '+0000') {
            return 'UTC';
        }

        if (preg_match('/^[+-]\d{2}(:?\d{2})?$/', $name)) {
            return 'GMT'.$name;
        }

        $tz = \IntlTimeZone::createTimeZone($name);
        if ($tz->getID() === 'Etc/Unknown' && $name !== 'Etc/Unknown') {
            return 'UTC';
        }

        return $name;
    }

    private static function locale(): string
    {
        return Locales::html(app()->getLocale());
    }
}
