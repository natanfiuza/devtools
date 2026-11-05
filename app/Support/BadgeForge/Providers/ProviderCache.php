<?php

namespace App\Support\BadgeForge\Providers;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Acesso tolerante ao store de cache (F4, ADR-080).
 *
 * Store fora do ar nao derruba a imagem: `get` devolve null e `put` e no-op,
 * e a camada de metricas segue com fetch direto/fallback.
 */
final class ProviderCache
{
    public function get(string $key): ?CachedProviderPayload
    {
        try {
            return CachedProviderPayload::fromArray(Cache::get($key));
        } catch (Throwable) {
            return null;
        }
    }

    public function put(string $key, CachedProviderPayload $payload, int $ttl): void
    {
        try {
            Cache::put($key, $payload->toArray(), max(1, $ttl));
        } catch (Throwable) {
            // Sem cache: a resposta segue com o payload recem-buscado.
        }
    }
}
