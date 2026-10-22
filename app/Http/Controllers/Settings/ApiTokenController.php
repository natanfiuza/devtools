<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiTokenRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens de acesso a API publica (ADR-050).
 *
 * O texto puro do token existe uma vez: vai no flash do POST e a tela o mostra
 * na requisicao seguinte. Depois disso o banco so guarda o hash, e quem perdeu
 * o valor cria outro token e revoga o antigo.
 */
final class ApiTokenController extends Controller
{
    /**
     * Unica ability concedida hoje; a API inteira exige `ai:query`.
     */
    public const ABILITY = 'ai:query';

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $tokens = $user->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->getKey(),
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'lastUsedAt' => $token->last_used_at?->toIso8601String(),
                'createdAt' => $token->created_at?->toIso8601String(),
            ]);

        $plainTextToken = $request->session()->get('api_token');

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $tokens,
            'plainTextToken' => is_string($plainTextToken)
                ? $plainTextToken
                : null,
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $user = $this->user($request);

        $token = $user->createToken(
            (string) $request->validated('name'),
            [self::ABILITY],
        );

        // Flash: visivel na tela uma unica vez, e nao no historico do navegador
        // (o redirect cria uma nova entrada e a pagina seguinte nao reenvia o
        // POST).
        $request->session()->flash('api_token', $token->plainTextToken);

        return redirect()->route('api-tokens.index');
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $user = $this->user($request);

        // Escopo pelo dono: o id de um token alheio responde 404.
        $user->tokens()->whereKey($token)->firstOrFail()->delete();

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
