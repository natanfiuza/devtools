<?php

namespace App\Jobs;

use App\Actions\Ai\FailAiQuery;
use App\Actions\Ai\SettleAiQuery;
use App\Contracts\Ai\LlmProvider;
use App\Enums\AiFailureCode;
use App\Enums\AiRequestStatus;
use App\Exceptions\Ai\AiException;
use App\Exceptions\Ai\InvalidContractException;
use App\Exceptions\Ai\PayloadCodecException;
use App\Exceptions\Ai\ProviderException;
use App\Exceptions\Ai\UnsafeSqlException;
use App\Models\AiRequest;
use App\Support\Ai\AiCacheRepository;
use App\Support\Ai\CanonicalPayload;
use App\Support\Ai\ContractValidator;
use App\Support\Ai\PayloadCodec;
use App\Support\Ai\PromptBuilder;
use App\Support\Ai\PromptLoader;
use App\Support\Ai\SqlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Executa a solicitacao fora do request HTTP.
 *
 * O construtor carrega apenas o ULID: o payload com schema e descricao fica
 * comprimido no banco, e o job o reidrata sob demanda. Assim a fila nao carrega
 * dado pessoal no payload serializado e um retry sempre le o estado atual.
 *
 * O job consulta o cache de novo antes de chamar o provedor. Isso cobre a
 * corrida em que duas contas pedem a mesma coisa ao mesmo tempo: as duas
 * passam pelo POST, uma preenche o cache e a outra encontra a resposta aqui,
 * recebendo o estorno integral da reserva.
 */
final class ProcessAiQuery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    /**
     * @var list<int>
     */
    public array $backoff;

    public bool $failOnTimeout = true;

    public function __construct(
        public string $aiRequestId,
    ) {
        $this->tries = max(1, (int) config('ai.job.tries', 2));
        $this->timeout = max(1, (int) config('ai.job.timeout', 90));

        $backoff = config('ai.job.backoff', [5, 15]);
        $this->backoff = is_array($backoff)
            ? array_values(array_map(
                static fn (mixed $seconds): int => max(0, (int) $seconds),
                $backoff,
            ))
            : [5, 15];
    }

    public function handle(
        PayloadCodec $codec,
        AiCacheRepository $cache,
        ContractValidator $contracts,
        SqlGuard $guard,
        LlmProvider $provider,
        PromptLoader $prompts,
        PromptBuilder $promptBuilder,
        SettleAiQuery $settle,
        FailAiQuery $fail,
    ): void {
        $request = AiRequest::query()->find($this->aiRequestId);

        if ($request === null || $request->isTerminal()) {
            return;
        }

        $target = $request->outputTarget()->firstOrFail();
        $payload = $this->payload($request, $codec);

        if ($payload === null) {
            $this->logFailure($request, new PayloadCodecException('The stored AI payload is missing or invalid.'));
            $fail($request, AiFailureCode::Internal);

            return;
        }

        try {
            $hit = $cache->find($payload, $request->dialect, $target->slug);

            if ($hit !== null) {
                $settle->fromCache($request, $hit);
                Log::info('AI request completed from cache.', [
                    'ai_request_id' => $request->id,
                    'cache_hit' => true,
                ]);

                return;
            }

            if ($request->status === AiRequestStatus::Queued) {
                $request->forceFill(['status' => AiRequestStatus::Processing])->save();
            }

            $prompt = $prompts->load($request->prompt_template);
            $completion = $provider->complete($promptBuilder->build($target, $payload, $prompt));
            $result = $contracts->validate(
                $this->decode($completion->content),
                $request->dialect,
                $target->slug,
            );

            // O alvo `sql` tem o gate lexical; os alvos de ORM sao validados
            // pelo contrato e nao geram SQL para executar.
            if ($target->slug === 'sql' && ! $result->needsClarification) {
                $guard->assertSafe($result->sql, $result->dialect);
            }

            $settled = $settle($request, $result, $completion->tokensIn, $completion->tokensOut);

            Log::info('AI request completed.', [
                'ai_request_id' => $settled->id,
                'cache_hit' => false,
                'tokens_in' => $settled->tokens_in,
                'tokens_out' => $settled->tokens_out,
            ]);
        } catch (ProviderException $exception) {
            $this->logFailure($request, $exception);

            if ($exception->retryable && $this->attempts() < $this->tries) {
                throw $exception;
            }

            $fail($request, AiFailureCode::ProviderUnavailable);
        } catch (UnsafeSqlException $exception) {
            $this->logFailure($request, $exception);
            $fail($request, AiFailureCode::UnsafeSql);
        } catch (InvalidContractException $exception) {
            $this->logFailure($request, $exception);
            $fail($request, AiFailureCode::InvalidContract);
        } catch (AiException $exception) {
            $this->logFailure($request, $exception);
            $fail($request, AiFailureCode::Internal);
        } catch (Throwable $exception) {
            // Falhas inesperadas (banco, serializacao, bug) valem uma nova
            // tentativa; se esgotarem, `failed()` estorna a reserva.
            $this->logFailure($request, $exception);

            throw $exception;
        }
    }

    /**
     * Chamado pelo worker quando as tentativas acabaram ou o job estourou o
     * tempo. A acao de falha e idempotente; um job que repete nao estorna duas
     * vezes.
     */
    public function failed(Throwable $exception): void
    {
        $request = AiRequest::query()->find($this->aiRequestId);

        if ($request === null) {
            Log::warning('AI request not found for failed job.', [
                'ai_request_id' => $this->aiRequestId,
                'exception' => $exception::class,
            ]);

            return;
        }

        $code = match (true) {
            $exception instanceof TimeoutExceededException => AiFailureCode::Timeout,
            $exception instanceof ProviderException => AiFailureCode::ProviderUnavailable,
            $exception instanceof UnsafeSqlException => AiFailureCode::UnsafeSql,
            $exception instanceof InvalidContractException => AiFailureCode::InvalidContract,
            default => AiFailureCode::Internal,
        };

        app(FailAiQuery::class)($request, $code);

        Log::warning('AI request failed.', [
            'ai_request_id' => $request->id,
            'error_code' => $code->value,
            'exception' => $exception::class,
        ]);
    }

    private function payload(AiRequest $request, PayloadCodec $codec): ?CanonicalPayload
    {
        $encoded = $request->canonical_payload;

        if ($encoded === null) {
            return null;
        }

        try {
            $payload = $codec->decode($encoded);
        } catch (PayloadCodecException) {
            return null;
        }

        return hash_equals($request->prompt_hash, $payload->hash()) ? $payload : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $content): array
    {
        try {
            $data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidContractException::because('invalid_json');
        }

        if (! is_array($data)) {
            throw InvalidContractException::because('invalid_json');
        }

        return $data;
    }

    private function logFailure(AiRequest $request, Throwable $exception): void
    {
        $context = [
            'ai_request_id' => $request->id,
            'exception' => $exception::class,
        ];

        if ($exception instanceof ProviderException) {
            $context['reason'] = $exception->reason;
            $context['retryable'] = $exception->retryable;
        }

        Log::warning('AI request failed.', $context);
    }
}
