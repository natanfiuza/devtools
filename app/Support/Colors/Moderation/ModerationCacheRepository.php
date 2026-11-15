<?php

namespace App\Support\Colors\Moderation;

use App\Exceptions\Ai\InvalidContractException;
use App\Exceptions\Ai\PayloadCodecException;
use App\Models\AiCache;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use JsonException;

/**
 * Cache da moderacao na tabela compartilhada `ai_cache` (F7, ADR-140).
 *
 * O `purpose` separa esta moderacao do AI SQL Builder. A linha pode ser
 * permanente (`expires_at` NULL) quando `cache_ttl_days` nao esta configurado;
 * a poda comum ignora NULL e o expurgo exige `--purpose` + `--all`.
 *
 * Assim como no cache do SQL Builder, o MD5 e chave de busca: o payload
 * guardado e comparado por completo antes de devolver a resposta.
 */
final class ModerationCacheRepository
{
    private const COMPRESSION_LEVEL = 6;

    private const MAX_DECODED_BYTES = 1_048_576;

    public function __construct(
        private readonly ModerationContractValidator $contracts,
    ) {}

    public function find(ModerationPayload $payload): ?ModerationCacheHit
    {
        $cache = AiCache::query()
            ->where('purpose', ModerationPayload::PURPOSE)
            ->where('prompt_hash', $payload->hash())
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($cache === null) {
            return null;
        }

        try {
            $stored = $this->decode($cache->canonical_payload);
        } catch (PayloadCodecException) {
            $cache->delete();

            return null;
        }

        if (! hash_equals($payload->json(), $stored->json())) {
            return null;
        }

        try {
            $result = $this->contracts->validate($cache->result());
        } catch (InvalidContractException) {
            // Linha corrompida nao pode envenenar todo comentario igual.
            $cache->delete();

            return null;
        }

        return new ModerationCacheHit($cache, $result);
    }

    public function store(
        ModerationPayload $payload,
        ModerationResult $result,
        int $tokensIn,
        int $tokensOut,
    ): AiCache {
        $hash = $payload->hash();
        $now = now();

        AiCache::query()->upsert(
            [[
                'prompt_hash' => $hash,
                'purpose' => ModerationPayload::PURPOSE,
                'canonical_payload' => $this->encode($payload),
                'response_json' => $result->json(),
                'model' => $payload->model,
                'prompt_version' => $payload->promptVersion,
                'tokens_in' => max(0, $tokensIn),
                'tokens_out' => max(0, $tokensOut),
                'hits' => 0,
                'last_hit_at' => null,
                'created_at' => $now,
                'expires_at' => $this->expiresAt(),
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

    private function expiresAt(): ?CarbonInterface
    {
        $days = config('colors.comments.moderation.cache_ttl_days');

        if (! is_int($days)) {
            return null;
        }

        return now()->addDays(max(0, $days));
    }

    private function encode(ModerationPayload $payload): string
    {
        $compressed = gzcompress($payload->json(), self::COMPRESSION_LEVEL);

        if ($compressed === false) {
            throw PayloadCodecException::encodeFailed();
        }

        return base64_encode($compressed);
    }

    private function decode(string $encoded): ModerationPayload
    {
        $compressed = base64_decode($encoded, true);

        if ($compressed === false || $compressed === '') {
            throw PayloadCodecException::decodeFailed();
        }

        $json = @gzuncompress($compressed, self::MAX_DECODED_BYTES);

        if ($json === false) {
            throw PayloadCodecException::decodeFailed();
        }

        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw PayloadCodecException::decodeFailed();
        }

        if (! is_array($data)) {
            throw PayloadCodecException::invalidShape();
        }

        return ModerationPayload::fromArray($data);
    }
}
