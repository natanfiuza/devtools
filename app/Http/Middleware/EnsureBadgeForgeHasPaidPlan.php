<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Criacao/edicao de provider customizado e recurso pago (F7, ADR-087).
 *
 * A leitura continua disponivel no Free (a lista mostra o CTA de upgrade), e
 * excluir segue permitido apos downgrade. So as escritas que criam ou editam
 * valor param aqui.
 */
final class EnsureBadgeForgeHasPaidPlan
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->effectivePlan()->isFree(), 403);

        return $next($request);
    }
}
