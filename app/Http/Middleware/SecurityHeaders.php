<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers de seguranca e nonce da CSP (ADR-052).
 *
 * O nonce e gerado por resposta e entregue ao Vite antes de a view renderizar:
 * o script inline do tema e os tags do @vite saem com o mesmo valor. A CSP
 * entra em `enforce` por padrao em producao; `report-only` serve de rollback
 * operacional sem deploy de codigo e `off` desliga o header no local.
 *
 * Os hosts liberados sao os que o site realmente usa: Google Analytics (so
 * depois do aceite, ADR-013) e Cloudflare Turnstile (cadastro, ADR-052).
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // O host e o esquema das URLs geradas (canonical, OG, sitemap) precisam
        // sair em https sempre que a configuracao exigir. Fica aqui, e nao so
        // no boot, para valer tambem em teste e em request que muda a config.
        if (config('security.force_https', false)) {
            URL::forceScheme('https');
        }

        $nonce = Str::random(32);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-Frame-Options', 'DENY');

        $maxAge = (int) config('security.hsts.max_age', 0);

        if ($request->isSecure() && $maxAge > 0) {
            $hsts = 'max-age='.$maxAge;

            if (config('security.hsts.include_subdomains', true)) {
                $hsts .= '; includeSubDomains';
            }

            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        $mode = (string) config('security.csp.mode', 'enforce');

        if ($mode !== 'off') {
            $header = $mode === 'report-only'
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $this->policy($nonce, $request->isSecure()));
        }

        return $response;
    }

    /**
     * Politica unica da aplicacao. `style-src 'unsafe-inline'` e necessario
     * para o bloco de cor do papel e para o tema do shadcn-vue; scripts seguem
     * presos ao nonce.
     */
    private function policy(string $nonce, bool $secure): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://www.googletagmanager.com https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https://www.google-analytics.com https://*.google-analytics.com",
            "font-src 'self' data:",
            "connect-src 'self' https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://challenges.cloudflare.com",
            'frame-src https://challenges.cloudflare.com',
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];

        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
