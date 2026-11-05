<?php

namespace App\Support\BadgeForge\Custom;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Acesso tolerante ao cache de providers customizados (F7, ADR-087).
 *
 * Store fora do ar nao derruba a imagem: `get` devolve null, `put` e no-op e
 * `forget` nao propaga.
 */
final class CustomProviderCache
{
    public function get(string $key): ?CachedCustomMetrics
    {
        try {
            return CachedCustomMetrics::fromArray(Cache::get($key));
        } catch (Throwable) {
            return null;
        }
    }

    public function put(string $key, CachedCustomMetrics $metrics, int $ttl): void
    {
        try {
            Cache::put($key, $metrics->toArray(), max(1, $ttl));
        } catch (Throwable) {
            // Sem cache: a resposta segue com os valores recem-buscados.
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
