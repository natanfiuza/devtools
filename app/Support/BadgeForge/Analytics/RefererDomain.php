<?php

namespace App\Support\BadgeForge\Analytics;

/**
 * Dominio de origem de uma visualizacao (F7, ADR-086).
 *
 * Do header `Referer` guardamos apenas o host em minusculas: nada de path,
 * query ou credencial. Header ausente, invalido ou host longo demais vira
 * `null`; a analise nao depende de referer para existir.
 */
final class RefererDomain
{
    public static function from(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $host = parse_url(trim($header), PHP_URL_HOST);

        if (! is_string($host)) {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));

        if ($host === '' || mb_strlen($host, 'UTF-8') > 253) {
            return null;
        }

        return $host;
    }
}
