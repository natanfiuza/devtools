<?php

namespace App\Actions\Ai;

use App\Enums\AiRequestStatus;
use App\Enums\LedgerEntryType;
use App\Enums\QuotaUnit;
use App\Exceptions\Ai\PayloadCodecException;
use App\Models\AiRequest;
use App\Models\User;
use App\Support\Ai\AiCacheHit;
use App\Support\Ai\AiCacheRepository;
use App\Support\Ai\AiLedger;
use App\Support\Ai\AiResult;
use App\Support\Ai\CanonicalPayload;
use App\Support\Ai\PayloadCodec;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fecha uma solicitacao que o provedor respondeu.
 *
 * O ajuste pago e a diferenca entre a reserva (`estimated_tokens`) e o
 * `tokens_in + tokens_out` devolvido pelo provedor. Cobrar a diferenca para
 * cima ou estornar para baixo mantem a cota igual ao consumo real; o Free nao
 * tem ajuste porque a unidade dele e o uso, nao o token.
 *
 * O cache e gravado **depois** do commit: uma falha ao gravar cache nao pode
 * desfazer uma resposta boa. O `canonical_payload` sai da solicitacao na
 * liquidacao; a copia comprimida so continua no cache, com prazo.
 */
final class SettleAiQuery
{
    public function __construct(
        private readonly PayloadCodec $codec,
        private readonly AiCacheRepository $cache,
    ) {}

    public function __invoke(
        AiRequest $request,
        AiResult $result,
        int $tokensIn,
        int $tokensOut,
    ): AiRequest {
        $tokensIn = max(0, $tokensIn);
        $tokensOut = max(0, $tokensOut);
        $payload = $this->payload($request);
        $id = $request->getKey();

        /** @var array{request: AiRequest, changed: bool} $outcome */
        $outcome = DB::transaction(function () use (
            $request,
            $id,
            $result,
            $tokensIn,
            $tokensOut,
        ): array {
            User::query()
                ->whereKey($request->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked = AiRequest::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isTerminal()) {
                return ['request' => $locked, 'changed' => false];
            }

            if ($locked->quota_unit === QuotaUnit::Tokens) {
                $actual = $tokensIn + $tokensOut;
                $reserved = $locked->reserved_tokens;

                if ($actual > $reserved) {
                    AiLedger::write($locked, LedgerEntryType::Debit, $actual - $reserved, 0);
                } elseif ($actual < $reserved) {
                    AiLedger::write($locked, LedgerEntryType::Refund, $reserved - $actual, 0);
                }
            }

            $locked->forceFill([
                'status' => AiRequestStatus::Done,
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'cache_hit' => false,
                'error_code' => null,
                'canonical_payload' => null,
                'response_json' => $result->json(),
            ])->save();

            return ['request' => $locked, 'changed' => true];
        });

        if ($outcome['changed'] && $payload !== null) {
            try {
                $this->cache->store($payload, $result, $tokensIn, $tokensOut);
            } catch (Throwable $exception) {
                // A mensagem da excecao pode carregar valores do payload; so o
                // tipo e o id entram no log.
                Log::warning('AI cache store failed.', [
                    'ai_request_id' => $outcome['request']->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $outcome['request'];
    }

    /**
     * Fecha uma solicitacao que encontrou a resposta no cache enquanto estava
     * na fila.
     *
     * O trabalho nao chegou a consumir a reserva, entao ela e devolvida
     * inteira. A resposta e a mesma que um hit no POST teria devolvido; o
     * `cache_hit` fica registrado para a tela.
     */
    public function fromCache(AiRequest $request, AiCacheHit $hit): AiRequest
    {
        $id = $request->getKey();

        return DB::transaction(function () use ($request, $id, $hit): AiRequest {
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
                'status' => AiRequestStatus::Done,
                'tokens_in' => max(0, $hit->cache->tokens_in),
                'tokens_out' => max(0, $hit->cache->tokens_out),
                'cache_hit' => true,
                'error_code' => null,
                'canonical_payload' => null,
                'response_json' => $hit->result->json(),
            ])->save();

            $this->cache->recordHit($hit->cache);

            return $locked;
        });
    }

    private function payload(AiRequest $request): ?CanonicalPayload
    {
        $encoded = $request->canonical_payload;

        if ($encoded === null) {
            return null;
        }

        try {
            $payload = $this->codec->decode($encoded);
        } catch (PayloadCodecException) {
            return null;
        }

        // Um payload adulterado nao pode gravar uma resposta em outro hash.
        return hash_equals($request->prompt_hash, $payload->hash()) ? $payload : null;
    }
}
