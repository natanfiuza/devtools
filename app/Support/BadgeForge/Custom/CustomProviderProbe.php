<?php

namespace App\Support\BadgeForge\Custom;

use App\Support\BadgeForge\Endpoint\EndpointFetchException;
use App\Support\BadgeForge\Endpoint\EndpointFetchReason;
use App\Support\BadgeForge\Endpoint\EndpointFetchRequest;
use App\Support\BadgeForge\Endpoint\EndpointHttpClient;
use App\Support\BadgeForge\Endpoint\EndpointTargetResolver;
use App\Support\BadgeForge\Endpoint\EndpointUrlException;
use App\Support\BadgeForge\Endpoint\EndpointUrlGuard;
use App\Support\BadgeForge\Endpoint\EndpointUrlReason;
use App\Support\BadgeForge\Endpoint\ResolvedEndpoint;
use App\Support\BadgeForge\Endpoint\SafeEndpointUrl;

/**
 * Amostra de um provider customizado no cadastro/edicao (F7, ADR-087).
 *
 * Shape errado e recusado (422): URL insegura, redirect, JSON invalido,
 * content-type errado, pointer ausente ou nao escalar. Rede, timeout, 429,
 * DNS que nao resolve e curl ausente sao tolerados — igual ao endpoint da F5:
 * o cadastro nao depende da origem estar no ar naquele segundo.
 */
final class CustomProviderProbe
{
    public function __construct(
        private readonly EndpointUrlGuard $urlGuard,
        private readonly EndpointTargetResolver $resolver,
        private readonly EndpointHttpClient $client,
        private readonly JsonDocument $document,
        private readonly CustomProviderGuard $guard,
    ) {}

    /**
     * @param  list<string>  $pathParams
     * @param  list<array{slug: string, pointer: string}>  $metrics
     */
    public function probe(
        CustomProviderUrlTemplate $template,
        array $pathParams,
        string $examplePath,
        array $metrics,
    ): void {
        $values = CustomPathParams::parse($examplePath, $pathParams);

        if ($values === null) {
            $this->guard->fail('example_path', 'example_path');
        }

        $rendered = $template->render($values);

        if ($rendered === null) {
            $this->guard->fail('example_path', 'example_path');
        }

        try {
            $url = $this->urlGuard->parse($rendered);
        } catch (EndpointUrlException $exception) {
            $this->guard->failUrl($exception);
        }

        $target = $this->resolve($url);

        if ($target === null) {
            return;
        }

        try {
            $body = $this->client->fetchBody(
                new EndpointFetchRequest($target),
                'badgeforge.custom_providers',
            );
        } catch (EndpointFetchException $exception) {
            if (in_array($exception->reason, [
                EndpointFetchReason::InvalidResponse,
                EndpointFetchReason::UnsupportedContentType,
                EndpointFetchReason::TooLarge,
                EndpointFetchReason::Redirected,
            ], true)) {
                $this->guard->fail('url_template', 'invalid_payload');
            }

            return;
        } catch (EndpointUrlException $exception) {
            $this->guard->failUrl($exception);
        }

        if ($body->notModified || $body->body === null) {
            return;
        }

        try {
            $document = $this->document->decode($body->body);
        } catch (EndpointFetchException) {
            $this->guard->fail('url_template', 'invalid_payload');
        }

        foreach ($metrics as $metric) {
            if (JsonPointer::extract($document, $metric['pointer']) === null) {
                $this->guard->fail('metrics', 'pointer_missing');
            }
        }
    }

    private function resolve(SafeEndpointUrl $url): ?ResolvedEndpoint
    {
        try {
            return $this->resolver->resolve($url);
        } catch (EndpointUrlException $exception) {
            if ($exception->reason === EndpointUrlReason::Unresolvable) {
                return null;
            }

            $this->guard->failUrl($exception);
        }
    }
}
