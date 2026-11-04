<?php

namespace App\Support\BadgeForge\Endpoint;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Acesso tolerante ao store de cache do endpoint (F5).
 *
 * Store fora do ar nao derruba a imagem: `get` devolve null, `put` vira
 * no-op e `forget` nao propaga, e as metricas seguem com fetch direto ou
 * fallback.
 */
final class EndpointCache
{
    public function get(string $key): ?CachedEndpointPayload
    {
        try {
            return CachedEndpointPayload::fromArray(Cache::get($key));
        } catch (Throwable) {
            return null;
        }
    }

    public function put(string $key, CachedEndpointPayload $payload, int $ttl): void
    {
        try {
            Cache::put($key, $payload->toArray(), max(1, $ttl));
        } catch (Throwable) {
            // Sem cache: a resposta segue com o payload recem-buscado.
        }
    }

    public function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable) {
            // Sem cache: a entrada expira sozinha.
        }
    }
}
