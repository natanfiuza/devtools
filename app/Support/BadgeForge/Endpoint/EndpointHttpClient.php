<?php

namespace App\Support\BadgeForge\Endpoint;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP do endpoint JSON (F5, ADR-084).
 *
 * A conexao e feita no IP que o resolver validou: `CURLOPT_RESOLVE` fixa
 * HOST:443:ADDRESS, `proxy => ''` ignora proxy do ambiente e
 * `protocols => ['https']` restringe o esquema. Sem redirect, sem curl a falha
 * e fechada. O corpo e lido com teto mesmo sem `Content-Length` (sink
 * limitado).
 */
final class EndpointHttpClient
{
    /**
     * @throws EndpointFetchException
     */
    public function fetch(EndpointFetchRequest $request, ?EndpointPayloadParser $parser = null): EndpointFetchResult
    {
        if ((bool) config('badgeforge.endpoint.require_curl', true) && ! $this->curlAvailable()) {
            throw new EndpointFetchException(EndpointFetchReason::CurlUnavailable);
        }

        $options = $this->options($request->target);
        $options['sink'] = new LimitedResponseSink(
            Utils::streamFor(Utils::tryFopen('php://temp', 'w+')),
            max(1, (int) config('badgeforge.endpoint.max_body_bytes', 524_288)),
        );

        $pending = Http::withOptions($options)
            ->withHeaders(['Accept' => 'application/json'])
            ->withUserAgent((string) config('badgeforge.endpoint.user_agent', 'devtools-badgeforge/1.0'))
            ->connectTimeout(max(1, (int) config('badgeforge.endpoint.connect_timeout', 3)))
            ->timeout(max(1, (int) config('badgeforge.endpoint.timeout', 5)))
            ->withoutRedirecting();

        if ($request->etag !== null && $request->etag !== '') {
            $pending = $pending->withHeaders(['If-None-Match' => $request->etag]);
        }

        try {
            $response = $pending->get($request->target->url->canonical);
        } catch (RequestException $exception) {
            if ($exception->getPrevious() instanceof ResponseBodyTooLarge) {
                throw new EndpointFetchException(EndpointFetchReason::TooLarge);
            }

            $message = strtolower($exception->getMessage());
            $timedOut = str_contains($message, 'timed out') || str_contains($message, 'curl error 28');

            throw new EndpointFetchException(
                $timedOut ? EndpointFetchReason::Timeout : EndpointFetchReason::Unavailable,
            );
        } catch (ConnectionException $exception) {
            $message = strtolower($exception->getMessage());
            $timedOut = str_contains($message, 'timed out') || str_contains($message, 'curl error 28');

            throw new EndpointFetchException(
                $timedOut ? EndpointFetchReason::Timeout : EndpointFetchReason::Unavailable,
            );
        }

        if ($response->status() === 304) {
            return new EndpointFetchResult(null, $request->etag, true);
        }

        if ($response->redirect()) {
            throw new EndpointFetchException(EndpointFetchReason::Redirected);
        }

        if (! $response->successful()) {
            throw new EndpointFetchException(
                $response->status() === 429
                    ? EndpointFetchReason::RateLimited
                    : EndpointFetchReason::Unavailable,
                $this->retryAfter($response),
            );
        }

        $this->assertContentType($response);

        $body = $this->bodyWithinLimit($response);

        return new EndpointFetchResult(
            payload: ($parser ?? new EndpointPayloadParser)->parse($body),
            etag: $this->etagFrom($response),
        );
    }

    /**
     * Opcoes Guzzle/curl da conexao pinada. Publico para o teste provar os
     * pinos sem depender de rede.
     *
     * @return array<string, mixed>
     */
    public function options(ResolvedEndpoint $target): array
    {
        return [
            // Guzzle 8 trata proxy, redirect e protocolos pelas proprias
            // opcoes e recusa os CURLOPT_* equivalentes no bloco curl.
            'allow_redirects' => false,
            'proxy' => '',
            'protocols' => ['https'],
            'curl' => [
                CURLOPT_RESOLVE => [$target->resolveEntry()],
            ],
        ];
    }

    public function curlAvailable(): bool
    {
        return extension_loaded('curl')
            && defined('CURLOPT_RESOLVE')
            && defined('CURLOPT_NOPROXY')
            && defined('CURLOPT_PROTOCOLS');
    }

    /**
     * @throws EndpointFetchException
     */
    private function assertContentType(Response $response): void
    {
        $header = strtolower(trim((string) $response->header('Content-Type')));
        $type = trim(explode(';', $header, 2)[0]);

        if ($type !== 'application/json' && ! str_ends_with($type, '+json')) {
            throw new EndpointFetchException(EndpointFetchReason::UnsupportedContentType);
        }
    }

    /**
     * @throws EndpointFetchException
     */
    private function bodyWithinLimit(Response $response): string
    {
        $maxBytes = max(1, (int) config('badgeforge.endpoint.max_body_bytes', 524_288));
        $contentLength = (int) ($response->header('Content-Length') ?: 0);

        if ($contentLength > $maxBytes) {
            throw new EndpointFetchException(EndpointFetchReason::TooLarge);
        }

        $stream = $response->toPsrResponse()->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = $stream->read($maxBytes + 1);

        if (strlen($body) > $maxBytes) {
            throw new EndpointFetchException(EndpointFetchReason::TooLarge);
        }

        return $body;
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

        return $timestamp === false ? null : max(0, $timestamp - now()->getTimestamp());
    }

    private function etagFrom(Response $response): ?string
    {
        $etag = trim((string) $response->header('ETag'));

        return $etag === '' ? null : $etag;
    }
}
