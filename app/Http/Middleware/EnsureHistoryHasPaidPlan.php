<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Historico e recurso de plano pago (ADR-051).
 *
 * A tela GET continua acessivel no Free - e ela que mostra o CTA de upgrade.
 * As escritas (toggle e apagar) param aqui: sem isto, uma conta Free chamaria
 * o PATCH direto e passaria a gravar consultas que ela nem consegue ver.
 */
class EnsureHistoryHasPaidPlan
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->effectivePlan()->isFree(), 403);

        return $next($request);
    }
}
