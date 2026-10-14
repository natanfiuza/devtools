<?php

namespace App\Http\Middleware;

use App\Support\Appearance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // O blade usa isto para marcar <html class="dark"> ja na primeira
        // resposta: sem cookie/coluna, o script inline decide por sistema.
        View::share('appearance', Appearance::resolve($request));

        return $next($request);
    }
}
