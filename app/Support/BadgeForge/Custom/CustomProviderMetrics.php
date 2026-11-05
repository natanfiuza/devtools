<?php

namespace App\Support\BadgeForge\Custom;

use App\Models\BadgeCustomProvider;
use App\Models\BadgeCustomProviderDefinition;
use App\Support\BadgeForge\Endpoint\EndpointFetchException;
use App\Support\BadgeForge\Endpoint\EndpointFetchRequest;
use App\Support\BadgeForge\Endpoint\EndpointHttpClient;
use App\Support\BadgeForge\Endpoint\EndpointTargetResolver;
use App\Support\BadgeForge\Endpoint\EndpointUrlException;
use App\Support\BadgeForge\Endpoint\EndpointUrlGuard;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Resolve as metricas de um provider customizado com cache (F7, ADR-087).
 *
 * Mesma maquina do endpoint da F5: HIT, MISS condicional com ETag, STALE e
 * fallback de 200. A diferenca e que todos os JSON Pointers sao extraidos de
 * uma unica resposta e apenas os escalares formatados entram no cache. Nenhum
 * caminho lanca para o leitor; o dono ve `unavailable` quando a origem falha.
 */
final class CustomProviderMetrics
{
    public function __construct(
        private readonly CustomProviderCache $cache,
        private readonly EndpointUrlGuard $urlGuard,
        private readonly EndpointTargetResolver $resolver,
        private readonly EndpointHttpClient $client,
        private readonly JsonDocument $document,
        private readonly CustomMetricFormatter $formatter,
    ) {}

    public function get(BadgeCustomProvider $provider, string $path): CustomMetricsResult
    {
        $ttl = $this->ttl();
        $staleTtl = max($ttl, (int) config('badgeforge.custom_providers.stale_ttl', 86_400));
        $fallbackTtl = max(1, (int) config('badgeforge.custom_providers.fallback_ttl', 60));
        $now = now()->getTimestamp();

        $template = CustomProviderUrlTemplate::from((string) $provider->url_template);
        $values = CustomPathParams::parse($path, $provider->pathParamNames());

        if ($template === null || $values === null) {
            return new CustomMetricsResult([], CustomMetricsResult::MISS, $fallbackTtl);
        }

        $rendered = $template->render($values);

        if ($rendered === null) {
            return new CustomMetricsResult([], CustomMetricsResult::MISS, $fallbackTtl);
        }

        try {
            $url = $this->urlGuard->parse($rendered);
        } catch (EndpointUrlException) {
            return new CustomMetricsResult([], CustomMetricsResult::MISS, $fallbackTtl);
        }

        $key = CustomProviderCacheKey::for(
            (string) $provider->getKey(),
            (int) $provider->metrics_version,
            $url->canonical,
        );
        $cached = $this->cache->get($key);

        if ($cached !== null && $cached->fallback) {
            return new CustomMetricsResult([], CustomMetricsResult::MISS, $fallbackTtl);
        }

        if ($cached !== null && $cached->isFresh($ttl, $now)) {
            return new CustomMetricsResult($cached->metrics(), CustomMetricsResult::HIT, $ttl);
        }

        $lock = $this->acquire($key);

        if ($lock === false) {
            return $cached !== null && $cached->isStaleUsable($now)
                ? new CustomMetricsResult($cached->metrics(), CustomMetricsResult::STALE, $ttl)
                : new CustomMetricsResult([], CustomMetricsResult::MISS, $fallbackTtl);
        }

        try {
            try {
                $target = $this->resolver->resolve($url);
                $body = $this->client->fetchBody(
                    new EndpointFetchRequest($target, $cached?->etag),
                    'badgeforge.custom_providers',
                );
            } catch (EndpointUrlException|EndpointFetchException $exception) {
                return $this->failed($key, $cached, $exception, $now, $ttl, $staleTtl, $fallbackTtl);
            }

            if ($body->notModified && $cached !== null) {
                $refreshed = $cached->refresh($now, $staleTtl);
                $this->cache->put($key, $refreshed, $staleTtl);

                return new CustomMetricsResult($refreshed->metrics(), CustomMetricsResult::HIT, $ttl);
            }

            if ($body->body === null) {
                return $this->fallback($key, $fallbackTtl, $fallbackTtl, $now);
            }

            try {
                $document = $this->document->decode($body->body);
            } catch (EndpointFetchException $exception) {
                return $this->failed($key, $cached, $exception, $now, $ttl, $staleTtl, $fallbackTtl);
            }

            $metrics = $this->extract($provider, $document);
            $fresh = CachedCustomMetrics::fromMetrics($metrics, $body->etag, $now, $staleTtl);
            $this->cache->put($key, $fresh, $staleTtl);

            return new CustomMetricsResult($fresh->metrics(), CustomMetricsResult::MISS, $ttl);
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
     * @param  array<mixed>  $document
     * @return array<string, string|null>
     */
    private function extract(BadgeCustomProvider $provider, array $document): array
    {
        $metrics = [];

        /** @var BadgeCustomProviderDefinition $definition */
        foreach ($provider->definitions()->get() as $definition) {
            $raw = JsonPointer::extract($document, (string) $definition->pointer);
            $metrics[(string) $definition->slug] = $this->formatter->format($raw, $definition->formatter);
        }

        return $metrics;
    }

    private function failed(
        string $key,
        ?CachedCustomMetrics $cached,
        EndpointUrlException|EndpointFetchException $exception,
        int $now,
        int $ttl,
        int $staleTtl,
        int $fallbackTtl,
    ): CustomMetricsResult {
        if ($cached !== null && $cached->isStaleUsable($now)) {
            return new CustomMetricsResult($cached->metrics(), CustomMetricsResult::STALE, $ttl);
        }

        $retryTtl = $exception instanceof EndpointFetchException && $exception->retryAfter !== null
            ? min(max($fallbackTtl, $exception->retryAfter), $staleTtl)
            : $fallbackTtl;

        return $this->fallback($key, $retryTtl, $retryTtl, $now);
    }

    /**
     * `false` = outra requisicao tem o lock; `true` = store sem lock;
     * `Lock` = adquirido e deve ser liberado.
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

    private function fallback(string $key, int $storedTtl, int $responseTtl, int $now): CustomMetricsResult
    {
        $this->cache->put($key, CachedCustomMetrics::fallback($now, $storedTtl), $storedTtl);

        return new CustomMetricsResult([], CustomMetricsResult::MISS, $responseTtl);
    }

    private function ttl(): int
    {
        $configured = max(1, (int) config('badgeforge.custom_providers.cache_ttl', 300));
        $ceiling = max(1, (int) config('badgeforge.custom_providers.max_cache_ttl', 3_600));

        return min($configured, $ceiling);
    }
}
