<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Proxies confiaveis lidos de `config/security.php` (ADR-052).
 *
 * O framework fixa a lista no boot (`TrustProxies::at`), o que impediria
 * mudar `TRUSTED_PROXIES` sem reiniciar e dificultaria o teste. Aqui a lista
 * e lida a cada request; so valem os headers X-Forwarded usados pelo proxy do
 * VPS (FOR, HOST, PORT e PROTO).
 */
final class TrustProxies extends Middleware
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;

    /**
     * @return array<int, string>|string|null
     */
    protected function proxies(): array|string|null
    {
        return config('security.trusted_proxies') ?: null;
    }
}
