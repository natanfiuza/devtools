<?php

namespace App\Support\BadgeForge\Analytics;

/**
 * Resolucao opcional de pais por IP (F7, ADR-086).
 *
 * O GeoIP e local (GeoLite2) e opcional: sem arquivo valido, toda leitura
 * devolve `null` e a analise segue apenas com os demais campos.
 */
interface CountryReader
{
    public function country(?string $ip): ?string;
}
