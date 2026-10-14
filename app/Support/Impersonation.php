<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Entrar como" - o admin abre a sessao de outra conta para ver o que ela ve
 * (ADR-025).
 *
 * O que identifica a impersonation e uma chave na sessao com o id do admin de
 * origem; o usuario autenticado passa a ser o alvo. Assim toda a autorizacao
 * existente continua valendo sem excecao: durante a impersonation a pessoa e o
 * alvo, e por isso o painel administrativo fica inacessivel (o alvo nao e
 * admin) - o que e proposital, e nao um efeito colateral.
 *
 * A chave e a unica forma de voltar: nao ha impersonation sem ela, e o alvo
 * nao pode abrir uma por cima da outra (encadear seria perder o caminho de
 * volta).
 */
final class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    /**
     * O admin de origem, quando existe impersonation em curso.
     */
    public static function actor(Request $request): ?User
    {
        $id = self::actorId($request);

        if ($id === null) {
            return null;
        }

        $actor = User::query()->find($id);

        // Admin apagado no meio do caminho: a sessao deixa de ser uma
        // impersonation (e o middleware de suspensao cuida do resto).
        if ($actor === null) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $actor;
    }

    public static function isActive(Request $request): bool
    {
        return self::actorId($request) !== null;
    }

    /**
     * Abre a sessao do alvo guardando de onde se veio.
     */
    public static function start(Request $request, User $actor, User $target): void
    {
        $request->session()->put(self::SESSION_KEY, $actor->getKey());

        Auth::guard('web')->login($target);

        // Sessao nova para o novo usuario: a anterior era do admin.
        $request->session()->regenerate();
    }

    /**
     * Volta para a sessao do admin de origem. Sem admin, nao faz nada (quem
     * chama decide o que responder).
     */
    public static function stop(Request $request, User $actor): void
    {
        Auth::guard('web')->logout();
        Auth::guard('web')->login($actor);

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();
    }

    private static function actorId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);

        return is_int($id) ? $id : null;
    }
}
