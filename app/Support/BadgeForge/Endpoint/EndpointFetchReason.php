<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Motivos de falha do fetch de endpoint (F5).
 *
 * `unavailable`, `rate_limited` e `timeout` sao falhas de rede/origem e
 * toleradas na criacao. `invalid_response`, `unsupported_content_type`,
 * `too_large` e `redirected` sao recusas de contrato/seguranca.
 */
enum EndpointFetchReason: string
{
    case Unavailable = 'unavailable';
    case RateLimited = 'rate_limited';
    case Timeout = 'timeout';
    case InvalidResponse = 'invalid_response';
    case UnsupportedContentType = 'unsupported_content_type';
    case TooLarge = 'too_large';
    case Redirected = 'redirected';
    case CurlUnavailable = 'curl_unavailable';
}
