<?php

namespace App\Http\Resources;

use App\Models\AiRequest;
use App\Support\Quota;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Consulta da API publica v1.
 *
 * O corpo e o mesmo da web (`AiQueryResource`), com o que so faz sentido para
 * um cliente externo: o alvo pedido, o `created_at` e o `poll_url` absoluto que
 * o cliente segue enquanto o status nao termina. `poll_url` nulo em `done` e
 * `failed` e o sinal para parar de consultar.
 *
 * @property AiRequest $resource
 */
final class ApiSqlQueryResource extends JsonResource
{
    public function __construct(
        AiRequest $aiRequest,
        private readonly Quota $quota,
    ) {
        parent::__construct($aiRequest);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AiRequest $model */
        $model = $this->resource;
        $terminal = $model->status->isTerminal();

        return [
            ...(new AiQueryResource($model, $this->quota))->toArray($request),
            'output_target' => $model->outputTarget->slug,
            'poll_url' => $terminal
                ? null
                : route('api.v1.sql-queries.show', ['aiRequest' => $model->id]),
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }
}
