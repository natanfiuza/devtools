<?php

namespace App\Support\Ai;

use App\Enums\SqlDialect;
use App\Exceptions\Ai\InvalidContractException;
use App\Exceptions\Ai\PayloadCodecException;
use App\Models\AiCache;
use Illuminate\Support\Carbon;

/**
 * Leitura e escrita do cache de respostas.
 *
 * O cache so e consultado por `prompt_hash` e so vale enquanto nao expirou. O
 * `upsert` preserva `hits`, `last_hit_at` e `created_at` quando a mesma
 * resposta e regravada; a expiracao e renovada.
 *
 * O MD5 e chave de busca, nao prova de identidade (ADR-016): antes de devolver
 * um hit, o payload guardado e comparado com o que chegou. Uma colisao de MD5
 * vira cache miss, nunca a resposta de outro schema.
 */
final class AiCacheRepository
{
    public function __construct(
        private readonly PayloadCodec $codec,
        private readonly ContractValidator $contracts,
    ) {}

    public function find(CanonicalPayload $payload, SqlDialect $dialect, string $target): ?AiCacheHit
    {
        $cache = AiCache::query()
            ->where('prompt_hash', $payload->hash())
            ->where('expires_at', '>', now())
            ->first();

        if ($cache === null) {
            return null;
        }

        try {
            $stored = $this->codec->decode($cache->canonical_payload);
        } catch (PayloadCodecException) {
            // Sem o payload nao ha como provar que a resposta e deste pedido.
            $cache->delete();

            return null;
        }

        if (! hash_equals($payload->json(), $stored->json())) {
            return null;
        }

        try {
            $result = $this->contracts->validate($cache->result(), $dialect, $target);
        } catch (InvalidContractException) {
            // Linha corrompida nao pode virar erro de todas as solicitacoes
            // futuras: apaga e deixa o pedido seguir para o provedor.
            $cache->delete();

            return null;
        }

        return new AiCacheHit($cache, $result);
    }

    public function store(
        CanonicalPayload $payload,
        AiResult $result,
        int $tokensIn,
        int $tokensOut,
    ): AiCache {
        $hash = $payload->hash();
        $now = now();
        $expiresAt = $now->copy()->addDays(max(0, (int) config('ai.cache_ttl_days', 30)));

        AiCache::query()->upsert(
            [[
                'prompt_hash' => $hash,
                'canonical_payload' => $this->codec->encode($payload),
                'response_json' => $result->json(),
                'model' => $payload->model,
                'prompt_version' => $payload->promptVersion,
                'tokens_in' => max(0, $tokensIn),
                'tokens_out' => max(0, $tokensOut),
                'hits' => 0,
                'last_hit_at' => null,
                'created_at' => $now,
                'expires_at' => $expiresAt,
            ]],
            ['prompt_hash'],
            [
                'canonical_payload',
                'response_json',
                'model',
                'prompt_version',
                'tokens_in',
                'tokens_out',
                'expires_at',
            ],
        );

        return AiCache::query()->where('prompt_hash', $hash)->firstOrFail();
    }

    public function recordHit(AiCache $cache): void
    {
        $cache->increment('hits', 1, ['last_hit_at' => now()]);
    }

    /**
     * Quantas linhas expiradas foram apagadas - usado pelo `ai:prune-cache`.
     */
    public function deleteExpired(?Carbon $before = null): int
    {
        return AiCache::query()
            ->where('expires_at', '<=', $before ?? now())
            ->delete();
    }
}
