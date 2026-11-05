<?php

namespace App\Support\BadgeForge\Analytics;

/**
 * Pseudonimizacao de IP e User-Agent (F7, ADR-086).
 *
 * HMAC-SHA256 com `APP_KEY`, o mesmo desenho do fingerprint de credencial: o
 * valor original nunca e persistido nem reversivel a partir de um dump sem a
 * chave. Ausente vira a string vazia para manter a coluna NOT NULL.
 */
final class PrivacyHash
{
    public static function of(?string $value): string
    {
        return hash_hmac('sha256', (string) $value, (string) config('app.key'));
    }
}
