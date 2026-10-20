<?php

namespace App\Http\Resources;

use App\Models\AiRequest;
use App\Support\Quota;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representacao de uma solicitacao de IA para o polling e para o POST.
 *
 * `poll_after_ms` so existe enquanto a solicitacao nao terminou; a tela usa o
 * valor do servidor como primeiro atraso e aplica o proprio backoff depois.
 * `quota` e o estado da conta na leitura - o Free aparece em usos, os pagos em
 * tokens.
 *
 * @property AiRequest $resource
 */
final class AiQueryResource extends JsonResource
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
            'id' => $model->id,
            'status' => $model->status->value,
            'cache_hit' => $model->cache_hit,
            'result' => $model->result(),
            'tokens' => [
                'in' => $model->tokens_in,
                'out' => $model->tokens_out,
            ],
            'poll_after_ms' => $terminal
                ? null
                : max(0, (int) config('ai.polling.initial_delay_ms', 1500)),
            'quota' => $this->quota->toArray(),
            'error_code' => $model->error_code?->value,
        ];
    }
}
