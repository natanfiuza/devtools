<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A API responde sempre JSON, mesmo quando o cliente esquece o cabecalho
 * `Accept`.
 *
 * Sem isso, o `Authenticate` calcula o redirect de login antes do handler de
 * excecoes: numa rota com `{locale}` o `route('login')` estoura e o cliente
 * recebe 500 em vez do envelope 401. O cabecalho aqui tambem garante que
 * erros de validacao e cota sigam o mesmo contrato.
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
