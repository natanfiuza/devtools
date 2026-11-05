<?php

namespace App\Support\BadgeForge\Analytics;

/**
 * Leitor de pais sem GeoIP (F7, ADR-086).
 *
 * Usado em testes e quando nenhum banco GeoLite2 esta instalado.
 */
final class NullCountryReader implements CountryReader
{
    public function country(?string $ip): ?string
    {
        return null;
    }
}
