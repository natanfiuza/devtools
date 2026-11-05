<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Criacao/edicao de provider customizado e recurso pago (F7, ADR-087).
 *
 * A leitura continua disponivel no Free (a lista mostra o CTA de upgrade), e
 * excluir segue permitido apos downgrade. So as escritas que criam ou editam
 * valor param aqui. A excecao e `AccessDeniedHttpException` de proposito: no
 * `api/*` ela vira o envelope `forbidden` (F9, ADR-090).
 */
final class EnsureBadgeForgeHasPaidPlan
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->effectivePlan()->isFree()) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
