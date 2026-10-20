<?php

namespace App\Support\Ai;

use App\Models\AiCache;

/**
 * Cache valido: a linha guardada e o contrato ja revalidado.
 *
 * O `result` e revalidado na leitura porque uma linha antiga ou adulterada nao
 * pode chegar a tela sem passar de novo pelas mesmas regras do provedor.
 */
final readonly class AiCacheHit
{
    public function __construct(
        public AiCache $cache,
        public AiResult $result,
    ) {}
}
