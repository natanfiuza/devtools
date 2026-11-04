<?php

namespace App\Support\BadgeForge\Endpoint;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Resolve o payload de um endpoint com cache por URL canonica (F5, ADR-083).
 *
 * Fluxo: entrada fresca -> HIT; vencida -> lock + requisicao condicional
 * (`If-None-Match`), 304 renova `fetched_at`; falha de rede/DNS com entrada
 * utilizavel -> STALE; sem entrada -> fallback de 200 com TTL curto. Nenhum
 * caminho lanca para o usuario; as metricas nunca devolvem 500.
 */
final class EndpointMetrics
{
    public function __construct(
        private readonly EndpointCache $cache,
        private readonly EndpointTargetResolver $resolver,
        private readonly EndpointHttpClient $client,
    ) {}

    public function get(SafeEndpointUrl $url): EndpointMetricsResult
    {
        $ttl = $this->ttl();
        $staleTtl = max($ttl, (int) config('badgeforge.endpoint.stale_ttl', 86_400));
        $fallbackTtl = max(1, (int) config('badgeforge.endpoint.fallback_ttl', 60));
        $key = EndpointCacheKey::for($url->canonical);
        $now = now()->getTimestamp();

        $cached = $this->cache->get($key);

        if ($cached !== null && $cached->fallback) {
            return new EndpointMetricsResult(null, EndpointMetricsResult::MISS, $fallbackTtl);
        }

        if ($cached !== null && $cached->isFresh($ttl, $now)) {
            return new EndpointMetricsResult($cached->payload(), EndpointMetricsResult::HIT, $ttl);
        }

        $lock = $this->acquire($key);

        if ($lock === false) {
            return $cached !== null && $cached->isStaleUsable($now)
                ? new EndpointMetricsResult($cached->payload(), EndpointMetricsResult::STALE, $ttl)
                : new EndpointMetricsResult(null, EndpointMetricsResult::MISS, $fallbackTtl);
        }

        try {
            try {
                $target = $this->resolver->resolve($url);
                $result = $this->client->fetch(new EndpointFetchRequest($target, $cached?->etag));
            } catch (EndpointUrlException|EndpointFetchException $exception) {
                return $this->failed($key, $cached, $exception, $now, $ttl, $staleTtl, $fallbackTtl);
            }

            if ($result->notModified && $cached !== null) {
                $refreshed = $cached->refresh($now, $staleTtl);
                $this->cache->put($key, $refreshed, $staleTtl);

                return new EndpointMetricsResult($refreshed->payload(), EndpointMetricsResult::HIT, $ttl);
            }

            if ($result->payload === null) {
                return $this->fallback($key, $fallbackTtl, $fallbackTtl, $now);
            }

            $fresh = CachedEndpointPayload::fromPayload($result->payload, $result->etag, $now, $staleTtl);
            $this->cache->put($key, $fresh, $staleTtl);

            return new EndpointMetricsResult($fresh->payload(), EndpointMetricsResult::MISS, $ttl);
        } finally {
            if ($lock instanceof Lock) {
                try {
                    $lock->release();
                } catch (Throwable) {
                    // Lock expirado: nada a fazer.
                }
            }
        }
    }

    private function failed(
        string $key,
        ?CachedEndpointPayload $cached,
        EndpointUrlException|EndpointFetchException $exception,
        int $now,
        int $ttl,
        int $staleTtl,
        int $fallbackTtl,
    ): EndpointMetricsResult {
        if ($cached !== null && $cached->isStaleUsable($now)) {
            return new EndpointMetricsResult($cached->payload(), EndpointMetricsResult::STALE, $ttl);
        }

        $retryTtl = $exception instanceof EndpointFetchException && $exception->retryAfter !== null
            ? min(max($fallbackTtl, $exception->retryAfter), $staleTtl)
            : $fallbackTtl;

        return $this->fallback($key, $retryTtl, $retryTtl, $now);
    }

    /**
     * `false` = outra requisicao tem o lock; `true` = store sem lock,
     * segue sem exclusao; `Lock` = adquirido e deve ser liberado.
     */
    private function acquire(string $key): Lock|bool
    {
        try {
            $lock = Cache::lock($key.':lock', 10);

            return $lock->get() ? $lock : false;
        } catch (Throwable) {
            return true;
        }
    }

    private function fallback(string $key, int $storedTtl, int $responseTtl, int $now): EndpointMetricsResult
    {
        $this->cache->put($key, CachedEndpointPayload::fallback($now, $storedTtl), $storedTtl);

        return new EndpointMetricsResult(null, EndpointMetricsResult::MISS, $responseTtl);
    }

    private function ttl(): int
    {
        $configured = max(1, (int) config('badgeforge.endpoint.cache_ttl', 300));
        $ceiling = max(1, (int) config('badgeforge.endpoint.max_cache_ttl', 3_600));

        return min($configured, $ceiling);
    }
}
