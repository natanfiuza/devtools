<?php

namespace App\Support\BadgeForge\Analytics;

use MaxMind\Db\Reader;
use Throwable;

/**
 * Leitor de pais com GeoLite2 local (F7, ADR-086).
 *
 * O arquivo e aberto na primeira leitura, nunca no boot, e qualquer problema
 * (ausente, corrompido, IP privado/reservado) degrada para `null`. O caminho
 * vem de `config/badgeforge.php`, nunca fixo no codigo.
 */
final class MaxMindCountryReader implements CountryReader
{
    private ?Reader $reader = null;

    private bool $loaded = false;

    public function __construct(private readonly string $path) {}

    public function country(?string $ip): ?string
    {
        if ($ip === null || ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        )) {
            return null;
        }

        $reader = $this->reader();

        if ($reader === null) {
            return null;
        }

        try {
            $record = $reader->get($ip);
        } catch (Throwable) {
            return null;
        }

        $iso = $record['country']['iso_code'] ?? null;

        if (! is_string($iso) || strlen($iso) !== 2) {
            return null;
        }

        return strtoupper($iso);
    }

    private function reader(): ?Reader
    {
        if ($this->loaded) {
            return $this->reader;
        }

        $this->loaded = true;

        try {
            if (is_file($this->path) && is_readable($this->path)) {
                $this->reader = new Reader($this->path);
            }
        } catch (Throwable) {
            $this->reader = null;
        }

        return $this->reader;
    }
}
