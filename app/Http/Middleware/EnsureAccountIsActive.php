<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Derruba a sessao de quem foi suspenso enquanto estava logado.
 *
 * Sem isto, suspender so valeria para o proximo login: quem ja estava dentro
 * continuaria dentro com a sessao aberta. Vale so nas rotas autenticadas - as
 * publicas nao consultam a conta, e derrubar ali surpreenderia quem so esta
 * lendo o site.
 *
 * Ver ADR-025.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isSuspended()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', __('auth.suspended'));
        }

        return $next($request);
    }
}
