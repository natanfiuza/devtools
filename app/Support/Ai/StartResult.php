<?php

namespace App\Support\Ai;

use App\Models\AiRequest;
use App\Support\Quota;

/**
 * Resultado do POST: a solicitacao criada (ou reaproveitada) e o estado da cota.
 *
 * `cacheHit` responde `200` porque a resposta ja existe; uma solicitacao nova
 * ou deduplicada responde `202` e a tela passa a consultar o status.
 */
final readonly class StartResult
{
    public function __construct(
        public AiRequest $request,
        public Quota $quota,
        public bool $cacheHit = false,
        public bool $deduplicated = false,
    ) {}

    public function httpStatus(): int
    {
        return $this->cacheHit ? 200 : 202;
    }
}
