<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiTokenRequest;
use App\Models\User;
use App\Support\Ai\ApiTokenUsageMetrics;
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
     * Ability do AI SQL Builder; a API `/api/v1/*` inteira a exige. E o
     * default de um token novo.
     */
    public const ABILITY = 'ai:query';

    /**
     * Abilities da API de gestao do BadgeForge (F8, ADR-088). Sao ortogonais
     * ao `ai:query`: cada superficie exige a sua e um token de um nao herda o
     * acesso do outro.
     */
    public const ABILITY_BADGEFORGE_READ = 'badgeforge:read';

    public const ABILITY_BADGEFORGE_WRITE = 'badgeforge:write';

    /**
     * @var list<string>
     */
    public const ABILITIES = [
        self::ABILITY,
        self::ABILITY_BADGEFORGE_READ,
        self::ABILITY_BADGEFORGE_WRITE,
    ];

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
                'requestsCount' => (int) ($token->getAttribute('requests_count') ?? 0),
                'sqlQueriesCount' => (int) ($token->getAttribute('sql_queries_count') ?? 0),
                'lastUsedAt' => $token->last_used_at?->toIso8601String(),
                'createdAt' => $token->created_at?->toIso8601String(),
            ]);

        $plainTextToken = $request->session()->get('api_token');

        $period = $request->query('period', ApiTokenUsageMetrics::PERIOD_CURRENT_MONTH);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $usageStats = app(ApiTokenUsageMetrics::class)->forUser(
            $user,
            is_string($period) ? $period : ApiTokenUsageMetrics::PERIOD_CURRENT_MONTH,
            is_string($startDate) ? $startDate : null,
            is_string($endDate) ? $endDate : null,
        );

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $tokens,
            'abilities' => self::ABILITIES,
            'stats' => [
                'totalRequests' => (int) $user->api_requests_count,
                'totalSqlQueries' => (int) $user->api_sql_queries_count,
                'activeTokens' => $tokens->count(),
            ],
            'usageStats' => $usageStats,
            'plainTextToken' => is_string($plainTextToken)
                ? $plainTextToken
                : null,
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $user = $this->user($request);

        // Sem escolha explicita o token continua sendo o do AI SQL Builder,
        // como antes da F8; a lista e filtrada pela allowlist da classe.
        $abilities = [];

        foreach ((array) $request->validated('abilities', [self::ABILITY]) as $ability) {
            if (is_string($ability) && in_array($ability, self::ABILITIES, true)) {
                $abilities[] = $ability;
            }
        }

        $token = $user->createToken(
            (string) $request->validated('name'),
            $abilities === [] ? [self::ABILITY] : $abilities,
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
