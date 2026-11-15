<?php

namespace App\Jobs;

use App\Actions\Colors\ApplyModerationResult;
use App\Contracts\Ai\LlmProvider;
use App\Enums\ColorCommentStatus;
use App\Exceptions\Ai\AiException;
use App\Exceptions\Ai\AiUnavailableException;
use App\Exceptions\Ai\InvalidContractException;
use App\Exceptions\Ai\PromptException;
use App\Exceptions\Ai\ProviderException;
use App\Models\ColorPaletteComment;
use App\Support\Ai\PromptLoader;
use App\Support\Colors\Moderation\ModerationCacheRepository;
use App\Support\Colors\Moderation\ModerationContractValidator;
use App\Support\Colors\Moderation\ModerationPayload;
use App\Support\Colors\Moderation\ModerationPromptBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Modera um comentario fora do request (F7, ADR-140).
 *
 * O job carrega apenas o ULID e a revisao. Chama o `LlmProvider` diretamente:
 * nao passa por `StartAiQuery`/`ProcessAiQuery`, nao reserva cota e nao grava
 * `usage_ledger` - a moderacao e custo da plataforma, nao do usuario.
 *
 * Falha definitiva do provedor, contrato invalido ou provedor ausente viram
 * `flagged`, nunca aprovacao. O texto do comentario e a resposta crua do modelo
 * nunca vao para log. `ShouldBeUnique` evita duas chamadas para a mesma revisao.
 */
final class ModerateColorComment implements ShouldBeUnique, ShouldQueue, ShouldQueueAfterCommit
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
        public string $commentId,
        public int $revision,
    ) {
        $this->tries = max(1, (int) config('colors.comments.moderation.job_tries', 3));
        $this->timeout = max(1, (int) config('colors.comments.moderation.job_timeout', 60));

        $backoff = max(0, (int) config('colors.comments.moderation.job_backoff_seconds', 30));
        $this->backoff = [$backoff];
    }

    public function uniqueId(): string
    {
        return $this->commentId.':'.$this->revision;
    }

    public function handle(
        PromptLoader $prompts,
        ModerationPromptBuilder $builder,
        ModerationContractValidator $contracts,
        ModerationCacheRepository $cache,
        ApplyModerationResult $apply,
        LlmProvider $provider,
    ): void {
        $comment = ColorPaletteComment::query()->find($this->commentId);

        if ($comment === null
            || (int) $comment->revision !== $this->revision
            || $comment->status !== ColorCommentStatus::Pending
            || $comment->deleted_at !== null) {
            return;
        }

        try {
            $prompt = $prompts->load($this->promptTemplate());
            $payload = $this->payload($comment, $prompt->version);

            $hit = $cache->find($payload);

            if ($hit !== null) {
                $cache->recordHit($hit->cache);
                $apply($comment->id, $this->revision, $hit->result);

                Log::info('Color comment moderated from cache.', [
                    'comment_id' => $comment->id,
                    'cache_hit' => true,
                ]);

                return;
            }

            $completion = $provider->complete($builder->build($payload, $prompt), $payload->model);
            $result = $contracts->validate($this->decode($completion->content));

            $cache->store($payload, $result, $completion->tokensIn, $completion->tokensOut);
            $apply($comment->id, $this->revision, $result);

            Log::info('Color comment moderated.', [
                'comment_id' => $comment->id,
                'cache_hit' => false,
                'decision' => $result->status->value,
            ]);
        } catch (AiUnavailableException $exception) {
            $this->logFailure($comment, $exception);
            $apply->flag($comment->id, $this->revision, 'provider_unconfigured');
        } catch (ProviderException $exception) {
            $this->logFailure($comment, $exception);

            if ($exception->retryable && $this->attempts() < $this->tries) {
                throw $exception;
            }

            $apply->flag($comment->id, $this->revision, 'provider_unavailable');
        } catch (InvalidContractException|PromptException $exception) {
            $this->logFailure($comment, $exception);
            $apply->flag($comment->id, $this->revision, 'invalid_contract');
        } catch (Throwable $exception) {
            // Falha inesperada merece nova tentativa; `failed()` decide o
            // estado final depois que a fila esgotar as tentativas.
            $this->logFailure($comment, $exception);

            throw $exception;
        }
    }

    /**
     * Chamado pelo worker quando as tentativas acabaram ou o tempo estourou.
     * Se o autor ja editou (revisao nova), o UPDATE condicional nao aplica.
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('Color comment moderation job failed.', [
            'comment_id' => $this->commentId,
            'revision' => $this->revision,
            'exception' => $exception::class,
        ]);

        app(ApplyModerationResult::class)->flag($this->commentId, $this->revision, 'job_failed');
    }

    private function promptTemplate(): string
    {
        $slug = config('colors.comments.moderation.prompt_slug');
        $slug = is_string($slug) && trim($slug) !== '' ? trim($slug) : 'colors_comment_moderation';

        $version = max(1, (int) config('colors.comments.moderation.prompt_version', 1));

        return $slug.'.v'.$version.'.md';
    }

    private function payload(ColorPaletteComment $comment, int $promptVersion): ModerationPayload
    {
        $model = config('colors.comments.moderation.model');
        $model = is_string($model) ? trim($model) : '';

        if ($model === '') {
            throw AiUnavailableException::notConfigured();
        }

        return new ModerationPayload(
            $comment->body,
            $comment->locale,
            $model,
            $promptVersion,
            max(1, (int) config('colors.comments.moderation.policy_version', 1)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $content): array
    {
        try {
            $data = json_decode($content, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidContractException::because('invalid_json');
        }

        if (! is_array($data)) {
            throw InvalidContractException::because('invalid_json');
        }

        return $data;
    }

    private function logFailure(ColorPaletteComment $comment, Throwable $exception): void
    {
        $context = [
            'comment_id' => $comment->id,
            'exception' => $exception::class,
        ];

        if ($exception instanceof ProviderException) {
            $context['reason'] = $exception->reason;
            $context['retryable'] = $exception->retryable;
        }

        if ($exception instanceof AiException) {
            Log::warning('Color comment moderation failed.', $context);

            return;
        }

        Log::warning('Color comment moderation failed unexpectedly.', $context);
    }
}
