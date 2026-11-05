<?php

namespace App\Support\BadgeForge\Providers;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Resolve a metrica de um provider com cache por URL resolvida (F4, ADR-080).
 *
 * Fluxo: entrada fresca -> HIT; vencida -> lock + requisicao condicional
 * (`If-None-Match`), 304 renova `fetched_at`; falha com entrada utilizavel ->
 * STALE; sem entrada -> fallback de 200 com TTL curto. Store indisponivel
 * degrada para fetch direto; nenhum caminho lanca para o usuario.
 */
final class ProviderMetrics
{
    public function __construct(
        private readonly ProviderCache $cache,
        private readonly ProviderClientRegistry $registry,
    ) {}

    public function get(ProviderFetchRequest $request): ProviderMetricsResult
    {
        $pair = $request->pair;
        $ttl = max(1, $pair->provider->effectiveCacheTtl());
        $staleTtl = max($ttl, (int) config('badgeforge.providers.stale_ttl', 86_400));
        $fallbackTtl = max(1, (int) config('badgeforge.providers.fallback_ttl', 60));
        $key = ProviderCacheKey::for($pair, $request->url, $request->credential);
        $now = now()->getTimestamp();

        $cached = $this->cache->get($key);

        if ($cached !== null && $cached->fallback) {
            return new ProviderMetricsResult(null, ProviderMetricsResult::MISS, $fallbackTtl);
        }

        if ($cached !== null && $cached->isFresh($ttl, $now)) {
            return new ProviderMetricsResult(
                $this->value($cached, $pair),
                ProviderMetricsResult::HIT,
                $ttl,
            );
        }

        $lock = $this->acquire($key);

        if ($lock === false) {
            return $cached !== null && $cached->isStaleUsable($now)
                ? new ProviderMetricsResult($this->value($cached, $pair), ProviderMetricsResult::STALE, $ttl)
                : new ProviderMetricsResult(null, ProviderMetricsResult::MISS, $fallbackTtl);
        }

        try {
            $client = $this->registry->for($pair->definition);

            if ($client === null) {
                return $this->fallback($key, $fallbackTtl, $fallbackTtl, $now);
            }

            try {
                $payload = $client->fetch(new ProviderFetchRequest(
                    pair: $pair,
                    params: $request->params,
                    url: $request->url,
                    credential: $request->credential,
                    etag: $cached?->etag,
                ));
            } catch (ProviderFetchException $exception) {
                if ($cached !== null && $cached->isStaleUsable($now)) {
                    return new ProviderMetricsResult(
                        $this->value($cached, $pair),
                        ProviderMetricsResult::STALE,
                        $ttl,
                    );
                }

                $retryTtl = $exception->retryAfter === null
                    ? $fallbackTtl
                    : min(max($fallbackTtl, $exception->retryAfter), $staleTtl);

                return $this->fallback($key, $retryTtl, $retryTtl, $now);
            }

            if ($payload->notModified && $cached !== null) {
                $refreshed = $cached->refresh($now, $staleTtl);
                $this->cache->put($key, $refreshed, $staleTtl);

                return new ProviderMetricsResult(
                    $this->value($refreshed, $pair),
                    ProviderMetricsResult::HIT,
                    $ttl,
                );
            }

            $fresh = new CachedProviderPayload(
                metrics: $payload->metrics,
                etag: $payload->etag,
                fetchedAt: $now,
                staleUntil: $now + $staleTtl,
            );
            $this->cache->put($key, $fresh, $staleTtl);

            return new ProviderMetricsResult(
                $this->value($fresh, $pair),
                ProviderMetricsResult::MISS,
                $ttl,
            );
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

    private function fallback(string $key, int $storedTtl, int $responseTtl, int $now): ProviderMetricsResult
    {
        $this->cache->put($key, CachedProviderPayload::fallback($now, $storedTtl), $storedTtl);

        return new ProviderMetricsResult(null, ProviderMetricsResult::MISS, $responseTtl);
    }

    private function value(CachedProviderPayload $payload, ProviderPair $pair): int|float|string|null
    {
        $value = $payload->metrics[$pair->definition->extractor_key] ?? null;

        return is_int($value) || is_float($value) || is_string($value) ? $value : null;
    }
}
