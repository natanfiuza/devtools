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
            $value->getTimezone()->getName(),
        );

        return $formatter->format($value->getTimestamp()) ?: $value->toDateTimeString();
    }

    private static function locale(): string
    {
        return Locales::html(app()->getLocale());
    }
}
