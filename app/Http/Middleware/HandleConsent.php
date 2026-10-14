<?php

namespace App\Http\Middleware;

use App\Support\Consent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apaga o cookie de consentimento quando ele chega com valor invalido.
 *
 * Nao ha decisao a tomar aqui: `App\Support\Consent` ja trata o valor
 * desconhecido como "sem decisao" - o visitante ve o banner de novo, que e o
 * estado seguro. O que falta e tirar o valor invalido do navegador, senao ele
 * se repete a cada requisicao.
 */
class HandleConsent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Consent::isCorrupt($request)) {
            $response->headers->setCookie(Cookie::forget(Consent::cookie()));
        }

        return $response;
    }
}
