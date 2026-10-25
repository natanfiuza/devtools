<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\StartAiQuery;
use App\Enums\AiRequestStatus;
use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Http\Controllers\Api\V1\Concerns\ResolvesApiIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiQueryRequest;
use App\Http\Resources\ApiSqlQueryResource;
use App\Models\AiRequest;
use App\Models\OutputTarget;
use App\Support\Locales;
use App\Support\Quota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Consultas do AI SQL Builder pela API publica (ADR-050).
 *
 * O POST tem o mesmo contrato assincrono da web: 200 quando o cache respondeu
 * na hora, 202 quando entrou na fila; o cliente segue o `poll_url`. A cota e a
 * do plano, debitada pelo mesmo `StartAiQuery`.
 *
 * O escopo de leitura e a conta (`user_id`): o token autenticado enxerga todas
 * as consultas da propria conta (criadas pela web ou por qualquer token da
 * conta). Consultas de outra conta respondem 404, para nao confirmar o id.
 */
final class SqlQueryController extends Controller
{
    use ResolvesApiIdentity;

    public function index(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        /** @var array{status?: string} $filters */
        $filters = $request->validate([
            'status' => ['sometimes', 'string', Rule::enum(AiRequestStatus::class)],
        ]);

        $perPage = max(1, min(100, (int) config('api.pagination.per_page', 20)));

        $query = AiRequest::query()
            ->where('user_id', $user->getKey());

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $queries = $query
            ->with('outputTarget')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $quota = Quota::for($user);

        return response()->json([
            'data' => $queries->getCollection()
                ->map(fn (AiRequest $aiRequest): array => (new ApiSqlQueryResource($aiRequest, $quota))->toArray($request))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $queries->currentPage(),
                'per_page' => $queries->perPage(),
                'total' => $queries->total(),
                'last_page' => $queries->lastPage(),
            ],
            'links' => [
                'next' => $queries->nextPageUrl(),
                'previous' => $queries->previousPageUrl(),
            ],
            'quota' => $quota->toArray(),
        ]);
    }

    public function store(StoreAiQueryRequest $request, StartAiQuery $start): JsonResponse
    {
        $user = $this->apiUser($request);
        $token = $this->apiToken($request);
        $data = $request->validated();

        $target = OutputTarget::query()
            ->where('slug', (string) $data['output_target'])
            ->where('enabled', true)
            ->firstOrFail();

        $rawLocale = (string) ($data['locale'] ?? '');
        $locale = Locales::isSupported($rawLocale)
            ? $rawLocale
            : (Locales::fromSlug($rawLocale) ?? Locales::preferred($request));

        $result = $start(
            $user,
            $target,
            SchemaSource::from((string) $data['schema_source']),
            SqlDialect::from((string) $data['dialect']),
            (string) $data['schema'],
            (string) $data['description'],
            $token->getKey(),
            $locale,
        );

        return (new ApiSqlQueryResource($result->request, $result->quota))
            ->response()
            ->setStatusCode($result->httpStatus());
    }

    public function show(Request $request, string $aiRequest): JsonResponse
    {
        $user = $this->apiUser($request);

        $model = AiRequest::query()
            ->whereKey($aiRequest)
            ->where('user_id', $user->getKey())
            ->with('outputTarget')
            ->firstOrFail();

        return (new ApiSqlQueryResource($model, Quota::for($user)))->response();
    }
}
