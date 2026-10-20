<?php

namespace App\Actions\Ai;

use App\Enums\AiRequestStatus;
use App\Enums\LedgerEntryType;
use App\Enums\QuotaUnit;
use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Exceptions\Ai\AiInputException;
use App\Exceptions\Ai\QuotaExceededException;
use App\Jobs\ProcessAiQuery;
use App\Models\AiRequest;
use App\Models\OutputTarget;
use App\Models\User;
use App\Support\Ai\AiCacheRepository;
use App\Support\Ai\AiConfiguration;
use App\Support\Ai\AiLedger;
use App\Support\Ai\Canonicalizer;
use App\Support\Ai\CanonicalPayload;
use App\Support\Ai\PayloadCodec;
use App\Support\Ai\PromptBuilder;
use App\Support\Ai\PromptLoader;
use App\Support\Ai\StartResult;
use App\Support\Ai\TokenEstimator;
use App\Support\Quota;
use Illuminate\Support\Facades\DB;

/**
 * Cria (ou reaproveita) uma solicitacao de IA e faz a reserva de cota.
 *
 * A transacao comeca travando a conta. Duas abas da mesma conta nao podem
 * avaliar cota e cache ao mesmo tempo: a segunda espera a primeira e, se o
 * hash for igual, recebe a solicitacao em voo em vez de debitar de novo.
 *
 * A ordem e deliberada: dedupe antes do cache (uma solicitacao em voo ja vai
 * produzir a resposta) e o cache antes dos limites do plano (um hit nao custa
 * nada e nao precisa passar pelo teto de cota). O dialeto e checado antes de
 * tudo porque a resposta de um alvo que nao aceita aquele dialeto nao serve.
 */
final class StartAiQuery
{
    public function __construct(
        private readonly Canonicalizer $canonicalizer,
        private readonly PromptLoader $prompts,
        private readonly PromptBuilder $promptBuilder,
        private readonly TokenEstimator $tokenEstimator,
        private readonly PayloadCodec $codec,
        private readonly AiCacheRepository $cache,
    ) {}

    public function __invoke(
        User $user,
        OutputTarget $outputTarget,
        SchemaSource $schemaSource,
        SqlDialect $dialect,
        string $schema,
        string $description,
    ): StartResult {
        AiConfiguration::ensureAvailable();
        $model = AiConfiguration::model();

        if (! $outputTarget->supportsDialect($dialect)) {
            throw AiInputException::unsupportedDialect();
        }

        $prompt = $this->prompts->load($outputTarget->prompt_template);
        $payload = $this->canonicalizer->canonicalize(
            $schemaSource,
            $dialect,
            $outputTarget->slug,
            $schema,
            $description,
            $model,
            $prompt->version,
        );

        $messages = $this->promptBuilder->build($outputTarget, $payload, $prompt);
        $estimatedTokens = $this->tokenEstimator->totalTokens($messages);

        $result = DB::transaction(function () use (
            $user,
            $outputTarget,
            $schemaSource,
            $dialect,
            $payload,
            $prompt,
            $estimatedTokens,
        ): StartResult {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $quota = Quota::for($lockedUser);
            $unit = QuotaUnit::from($quota->unit());

            $cutoff = now()->subSeconds(max(0, (int) config('ai.inflight_ttl_seconds', 600)));

            $existing = AiRequest::query()
                ->where('user_id', $lockedUser->getKey())
                ->where('prompt_hash', $payload->hash())
                ->pending()
                ->where('created_at', '>=', $cutoff)
                ->orderByDesc('created_at')
                ->first();

            if ($existing !== null) {
                return new StartResult($existing, $quota, false, true);
            }

            $hit = $this->cache->find($payload, $dialect, $outputTarget->slug);

            if ($hit !== null) {
                $request = AiRequest::query()->create([
                    'user_id' => $lockedUser->getKey(),
                    'output_target_id' => $outputTarget->getKey(),
                    'status' => AiRequestStatus::Done,
                    'dialect' => $dialect,
                    'schema_source' => $schemaSource,
                    'prompt_hash' => $payload->hash(),
                    'prompt_version' => $prompt->version,
                    'prompt_template' => $prompt->template,
                    'model' => $payload->model,
                    'estimated_tokens' => max(0, $hit->cache->tokens_in + $hit->cache->tokens_out),
                    'reserved_tokens' => 0,
                    'reserved_uses' => 0,
                    'quota_unit' => $unit,
                    'tokens_in' => max(0, $hit->cache->tokens_in),
                    'tokens_out' => max(0, $hit->cache->tokens_out),
                    'cache_hit' => true,
                    'error_code' => null,
                    'history_visible' => false,
                    'canonical_payload' => null,
                    'response_json' => $hit->result->json(),
                ]);

                $this->cache->recordHit($hit->cache);

                return new StartResult($request, $quota, true);
            }

            $this->assertWithinPlanLimits($quota, $payload, $estimatedTokens, $unit);

            $request = AiRequest::query()->create([
                'user_id' => $lockedUser->getKey(),
                'output_target_id' => $outputTarget->getKey(),
                'status' => AiRequestStatus::Queued,
                'dialect' => $dialect,
                'schema_source' => $schemaSource,
                'prompt_hash' => $payload->hash(),
                'prompt_version' => $prompt->version,
                'prompt_template' => $prompt->template,
                'model' => $payload->model,
                'estimated_tokens' => $estimatedTokens,
                'reserved_tokens' => $unit === QuotaUnit::Tokens ? $estimatedTokens : 0,
                'reserved_uses' => $unit === QuotaUnit::Uses ? 1 : 0,
                'quota_unit' => $unit,
                'tokens_in' => null,
                'tokens_out' => null,
                'cache_hit' => false,
                'error_code' => null,
                'history_visible' => false,
                'canonical_payload' => $this->codec->encode($payload),
                'response_json' => null,
            ]);

            AiLedger::write(
                $request,
                LedgerEntryType::Debit,
                $unit === QuotaUnit::Tokens ? $estimatedTokens : 0,
                $unit === QuotaUnit::Uses ? 1 : 0,
            );

            return new StartResult($request, Quota::for($lockedUser));
        });

        // So uma solicitacao nova vai para a fila. Cache hit ja nasce `done` e
        // dedupe devolve uma que ja tem job (ou esta em execucao).
        if (! $result->cacheHit && ! $result->deduplicated) {
            ProcessAiQuery::dispatch($result->request->id)->afterCommit();
        }

        return $result;
    }

    private function assertWithinPlanLimits(
        Quota $quota,
        CanonicalPayload $payload,
        int $estimatedTokens,
        QuotaUnit $unit,
    ): void {
        $maxSchemaBytes = min(
            max(0, (int) config('ai.limits.max_schema_bytes', 65536)),
            max(0, $quota->plan->max_schema_bytes),
        );

        if (strlen($payload->schema) > $maxSchemaBytes) {
            throw AiInputException::schemaTooLarge();
        }

        $maxDescriptionChars = min(
            max(0, (int) config('ai.limits.max_description_chars', 4000)),
            max(0, $quota->plan->max_prompt_chars),
        );

        if (mb_strlen($payload->description, 'UTF-8') > $maxDescriptionChars) {
            throw AiInputException::descriptionTooLong();
        }

        $remaining = $quota->remaining();

        if ($unit === QuotaUnit::Uses) {
            if ($remaining < 1) {
                throw QuotaExceededException::forUnit($unit, 1);
            }

            return;
        }

        $required = max(1, $estimatedTokens);

        if ($remaining < $required) {
            throw QuotaExceededException::forUnit($unit, $required);
        }
    }
}
