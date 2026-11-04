<?php

namespace App\Support\BadgeForge\Providers;

use RuntimeException;

/**
 * Falha controlada ao consultar um provider (F4, ADR-080).
 *
 * O codigo e estavel e vira fallback/stale na camada de cache; nunca chega
 * ao usuario como 500. `retryAfter` e apenas o inteiro de segundos do
 * header `Retry-After`, sem o corpo da resposta.
 */
final class ProviderFetchException extends RuntimeException
{
    public const string UNAVAILABLE = 'unavailable';

    public const string RATE_LIMITED = 'rate_limited';

    public const string NOT_FOUND = 'not_found';

    public const string UNAUTHORIZED = 'unauthorized';

    public const string INVALID_RESPONSE = 'invalid_response';

    public const string TIMEOUT = 'timeout';

    public const string UNSAFE_URL = 'unsafe_url';

    public function __construct(
        public readonly string $reason,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($reason);
    }
}
