<?php

namespace App\Http\Controllers\Api;

use App\Actions\Ai\StartAiQuery;
use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiQueryRequest;
use App\Http\Resources\AiQueryResource;
use App\Models\AiRequest;
use App\Models\OutputTarget;
use App\Models\User;
use App\Support\Quota;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de sessao do AI SQL Builder (ADR-042).
 *
 * Nao ha token de API: a rota vive no grupo `web` e usa o cookie de sessao e o
 * CSRF de sempre. O `store` devolve 200 quando o cache respondeu na hora e 202
 * quando a solicitacao entrou na fila; o `show` devolve 200 em qualquer estado,
 * porque o status e o dado que a tela espera.
 *
 * O dono e sempre o `user_id`: uma solicitacao de outra conta responde 404, e
 * nao 403, para nao confirmar que o id existe.
 */
final class AiQueryController extends Controller
{
    public function store(StoreAiQueryRequest $request, StartAiQuery $start): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $data = $request->validated();

        $target = OutputTarget::query()
            ->where('slug', (string) $data['output_target'])
            ->where('enabled', true)
            ->firstOrFail();

        $result = $start(
            $user,
            $target,
            SchemaSource::from((string) $data['schema_source']),
            SqlDialect::from((string) $data['dialect']),
            (string) $data['schema'],
            (string) $data['description'],
        );

        return (new AiQueryResource($result->request, $result->quota))
            ->response()
            ->setStatusCode($result->httpStatus());
    }

    public function show(Request $request, string $aiRequest): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $model = AiRequest::query()
            ->whereKey($aiRequest)
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        return (new AiQueryResource($model, Quota::for($user)))->response();
    }
}
