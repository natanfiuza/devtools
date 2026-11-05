<?php

namespace App\Support\BadgeForge\Custom;

/**
 * Chave de cache de um provider customizado (F7, ADR-087).
 *
 * O namespace inclui o ULID e `metrics_version`; o template so entra como
 * sha256, nunca legivel. Editar uma metrica incrementa a versao e invalida o
 * valor antigo sem varrer o store.
 */
final class CustomProviderCacheKey
{
    public static function for(string $providerId, int $metricsVersion, string $canonicalUrl): string
    {
        return 'badgeforge:custom:v1:'.$providerId.':'.max(1, $metricsVersion).':'.hash('sha256', $canonicalUrl);
    }
}
