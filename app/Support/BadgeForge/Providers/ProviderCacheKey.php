<?php

namespace App\Support\BadgeForge\Providers;

/**
 * Chave de cache de uma consulta a provider (F4, ADR-080).
 *
 * A identidade e `provider | URL resolvida | public|fingerprint`. O token
 * nunca entra na chave: no modo privado entra apenas o HMAC-SHA256 do
 * `ProviderCredential`. Como a URL ja e canonica (params codificados uma vez),
 * as quatro definicoes do GitHub compartilham a mesma entrada.
 */
final class ProviderCacheKey
{
    public static function for(ProviderPair $pair, string $url, ?ProviderCredential $credential): string
    {
        $identity = $credential === null
            ? 'public'
            : 'private:'.$credential->fingerprint((string) $pair->provider->slug);

        return 'badgeforge:provider:'.hash(
            'sha256',
            $pair->provider->slug.'|'.$url.'|'.$identity,
        );
    }
}
