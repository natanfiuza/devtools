<?php

namespace App\Actions\Ai;

use App\Enums\AiFailureCode;
use App\Enums\AiRequestStatus;
use App\Models\AiRequest;
use App\Models\User;
use App\Support\Ai\AiLedger;
use Illuminate\Support\Facades\DB;

/**
 * Fecha uma solicitacao que nao sera concluida.
 *
 * A reserva e devolvida na unidade em que foi debitada, inclusive quando o
 * erro acontece depois de o job comecar: o provedor pode ter cobrado, mas a
 * pessoa nao recebeu resposta. A operacao e idempotente - um job que repete
 * depois de outro ja ter falhado nao estorna duas vezes.
 */
final class FailAiQuery
{
    public function __invoke(AiRequest $request, AiFailureCode $code): AiRequest
    {
        $id = $request->getKey();

        return DB::transaction(function () use ($request, $id, $code): AiRequest {
            User::query()
                ->whereKey($request->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked = AiRequest::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isTerminal()) {
                return $locked;
            }

            AiLedger::refundReservation($locked);

            $locked->forceFill([
                'status' => AiRequestStatus::Failed,
                'error_code' => $code,
                'tokens_in' => null,
                'tokens_out' => null,
                'cache_hit' => false,
                'canonical_payload' => null,
                'response_json' => null,
            ])->save();

            return $locked;
        });
    }
}
