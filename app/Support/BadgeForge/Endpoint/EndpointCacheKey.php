<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Chave de cache de um endpoint (F5, ADR-083).
 *
 * So o hash da URL canonica entra na chave; host, path e query nunca ficam
 * legiveis no store.
 */
final class EndpointCacheKey
{
    public static function for(string $canonicalUrl): string
    {
        return 'badgeforge:endpoint:v1:'.hash('sha256', $canonicalUrl);
    }
}
