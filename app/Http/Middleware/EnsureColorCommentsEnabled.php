<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Superficie de comentarios desligada por padrao (F7, ADR-143).
 *
 * Desligada, todas as rotas de comentario/denuncia/mute respondem o mesmo 404
 * - nem o fato de a funcionalidade existir e revelado. A pagina publica
 * tambem nao consulta a thread nem reproduz os formularios.
 */
final class EnsureColorCommentsEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('colors.comments.enabled', false), 404);

        return $next($request);
    }
}
