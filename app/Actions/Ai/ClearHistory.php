<?php

namespace App\Actions\Ai;

use App\Enums\AiRequestStatus;
use App\Models\AiRequest;
use App\Models\User;

/**
 * Apaga o historico de consultas de uma conta.
 *
 * So linhas terminais saem: uma consulta `queued`/`processing` ainda e dona da
 * reserva de cota e do job, e apaga-la deixaria o worker sem registro. O
 * livro-razao (`usage_ledger`) fica intacto: consumo e contabilidade, e nao
 * historico.
 */
final class ClearHistory
{
    public function __invoke(User $user): int
    {
        return AiRequest::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [
                AiRequestStatus::Done->value,
                AiRequestStatus::Failed->value,
            ])
            ->delete();
    }
}
