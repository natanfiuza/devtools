<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Support\BadgeForge\Providers\ProviderCredential;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;
use App\Support\BadgeForge\Providers\ProviderMetricFormatter;
use App\Support\BadgeForge\Providers\ProviderPayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Transporte HTTP comum dos providers (F4, ADR-080).
 *
 * Sem redirect, com timeouts de config, corpo limitado e JSON estrito. Cada
 * falha vira `ProviderFetchException` com codigo estavel: a camada de cache
 * decide stale/fallback, nunca um 500.
 */
abstract class AbstractProviderClient implements ProviderClient
{
    public function __construct(protected readonly ProviderMetricFormatter $formatter) {}

    public function fetch(ProviderFetchRequest $request): ProviderPayload
    {
        $pending = Http::withHeaders($this->headers($request))
            ->withUserAgent((string) config('badgeforge.providers.user_agent', 'devtools-badgeforge/1.0'))
            ->connectTimeout(max(1, (int) config('badgeforge.providers.connect_timeout', 3)))
            ->timeout(max(1, (int) config('badgeforge.providers.timeout', 5)))
            ->withoutRedirecting();

        if ($request->credential instanceof ProviderCredential) {
            $pending = $this->authenticate($pending, $request->credential);
        }

        if ($request->etag !== null && $request->etag !== '') {
            $pending = $pending->withHeaders(['If-None-Match' => $request->etag]);
        }

        try {
            $response = $pending->get($request->url);
        } catch (ConnectionException $exception) {
            $message = strtolower($exception->getMessage());

            $timedOut = str_contains($message, 'timed out') || str_contains($message, 'curl error 28');

            throw new ProviderFetchException(
                $timedOut ? ProviderFetchException::TIMEOUT : ProviderFetchException::UNAVAILABLE,
            );
        } catch (RequestException) {
            throw new ProviderFetchException(ProviderFetchException::UNAVAILABLE);
        }

        if ($response->status() === 304) {
            return new ProviderPayload([], $request->etag, true);
        }

        if (! $response->successful()) {
            throw new ProviderFetchException(
                $this->reasonFor($response),
                $this->retryAfter($response),
            );
        }

        $maxBytes = max(1, (int) config('badgeforge.providers.max_body_bytes', 1_048_576));
        $contentLength = (int) ($response->header('Content-Length') ?: 0);
        $body = $response->body();

        if ($contentLength > $maxBytes || strlen($body) > $maxBytes) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        try {
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        if (! is_array($data)) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        return new ProviderPayload(
            metrics: $this->project($data, $request),
            etag: $this->etagFrom($response),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function headers(ProviderFetchRequest $request): array
    {
        return ['Accept' => 'application/json'];
    }

    protected function authenticate(PendingRequest $pending, ProviderCredential $credential): PendingRequest
    {
        return $pending;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|float|string|null>
     */
    abstract protected function project(array $data, ProviderFetchRequest $request): array;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && is_finite($value)) {
            return (int) $value;
        }

        throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function number(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        $number = (float) $value;

        if (! is_finite($number) || $number < 0) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        return $number;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        return $value;
    }

    private function reasonFor(Response $response): string
    {
        return match ($response->status()) {
            401 => ProviderFetchException::UNAUTHORIZED,
            403 => $response->header('X-RateLimit-Remaining') === '0'
                ? ProviderFetchException::RATE_LIMITED
                : ProviderFetchException::UNAUTHORIZED,
            404 => ProviderFetchException::NOT_FOUND,
            429 => ProviderFetchException::RATE_LIMITED,
            default => ProviderFetchException::UNAVAILABLE,
        };
    }

    private function retryAfter(Response $response): ?int
    {
        $header = trim((string) $response->header('Retry-After'));

        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return max(0, (int) $header);
        }

        $timestamp = strtotime($header);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - now()->getTimestamp());
    }

    private function etagFrom(Response $response): ?string
    {
        $etag = trim((string) $response->header('ETag'));

        return $etag === '' ? null : $etag;
    }
}
