<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contabiliza requisicoes autenticadas na API publica v1 (ADR-057).
 *
 * Roda dentro do grupo `/api/v1` apos `auth:sanctum`, `active`, `verified` e
 * `abilities:ai:query`: so incrementa chamadas cujo token e conta sao validos.
 * Mantem o contador tanto no token (`personal_access_tokens.requests_count`)
 * quanto no acumulado vitalicio da conta (`users.api_requests_count`), para
 * que revogar um token nao diminua o historico total de requisicoes da conta.
 */
final class TrackApiTokenUsage
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $token = $user->currentAccessToken();

            DB::table('personal_access_tokens')
                ->where('id', $token->getKey())
                ->increment('requests_count');

            DB::table('users')
                ->where('id', $user->getKey())
                ->increment('api_requests_count');
        }

        return $next($request);
    }
}
