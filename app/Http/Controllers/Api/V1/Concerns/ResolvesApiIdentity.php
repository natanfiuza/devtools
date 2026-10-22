<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Conta e token do pedido autenticado da API v1.
 *
 * O middleware `auth:sanctum` ja garantiu o Bearer token; os helpers existem
 * para o tipo ficar explicito no controlador e para uma futura rota sem token
 * responder 401 pelo envelope, em vez de estourar em `null`.
 */
trait ResolvesApiIdentity
{
    private function apiUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * O token que fez a chamada.
     *
     * A superficie `/api/v1` e token-only (`sanctum.stateful` vazio), entao
     * `currentAccessToken()` e sempre um `PersonalAccessToken`; o middleware
     * `auth:sanctum` ja recusou quem chegou sem Bearer valido. Nao ha caminho
     * de sessao aqui, e por isso o filtro por `id` nunca recebe nulo.
     */
    private function apiToken(Request $request): PersonalAccessToken
    {
        return $this->apiUser($request)->currentAccessToken();
    }
}
