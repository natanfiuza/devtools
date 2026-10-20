<?php

namespace App\Support\Ai;

use App\Enums\LedgerEntryType;
use App\Enums\QuotaUnit;
use App\Models\AiRequest;
use App\Models\UsageLedger;

/**
 * Escrita do livro-razao de uma solicitacao de IA.
 *
 * Toda linha usa o ULID da solicitacao e o **mesmo `created_at`** dela. A cota
 * paga conta a janela da assinatura por `created_at` (App\Support\Quota); se o
 * estorno de uma liquidacao fosse carimbado com a hora do job, um pedido feito
 * no fim do periodo poderia cair no periodo seguinte.
 *
 * Nao existe saldo em coluna: debito, estorno e ajuste sao linhas imutaveis.
 */
final class AiLedger
{
    public static function write(
        AiRequest $request,
        LedgerEntryType $type,
        int $tokens,
        int $uses,
    ): void {
        $tokens = max(0, $tokens);
        $uses = max(0, $uses);

        if ($tokens === 0 && $uses === 0) {
            return;
        }

        UsageLedger::query()->forceCreate([
            'user_id' => $request->user_id,
            'ai_request_id' => null,
            'ai_request_ulid' => $request->id,
            'type' => $type,
            'tokens' => $tokens,
            'uses' => $uses,
            'created_at' => $request->created_at ?? now(),
        ]);
    }

    /**
     * Devolve a reserva inteira na unidade em que ela foi debitada.
     *
     * Usado quando o job encontra a resposta no cache ou quando o provedor
     * falha: nos dois casos o trabalho nao chegou a consumir o que foi
     * reservado. O Free nao recebe ajuste por tokens em nenhum caso.
     */
    public static function refundReservation(AiRequest $request): void
    {
        if ($request->quota_unit === QuotaUnit::Uses) {
            self::write($request, LedgerEntryType::Refund, 0, $request->reserved_uses);

            return;
        }

        self::write($request, LedgerEntryType::Refund, $request->reserved_tokens, 0);
    }
}
