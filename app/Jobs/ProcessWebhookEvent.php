<?php

namespace App\Jobs;

use App\Actions\Payments\HandleWebhookEvent;
use App\Enums\WebhookEventStatus;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Processa a notificacao guardada, fora do request (F6).
 *
 * O evento fica `processing` enquanto o job roda. Falha volta para a fila com
 * backoff; a acao e idempotente, entao repetir e seguro. Depois das tentativas,
 * o evento fica `failed` com a mensagem (sem corpo de resposta nem segredo) e
 * aparece no admin.
 */
final class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 60;

    public function __construct(private readonly int $eventId) {}

    public function handle(HandleWebhookEvent $handle): void
    {
        $event = WebhookEvent::query()->find($this->eventId);

        if (! $event instanceof WebhookEvent) {
            return;
        }

        if (in_array($event->status, [WebhookEventStatus::Processed, WebhookEventStatus::Ignored], true)) {
            return;
        }

        $event->forceFill([
            'status' => WebhookEventStatus::Processing,
            'attempts' => $event->attempts + 1,
            'error' => null,
        ])->save();

        try {
            $status = $handle($event);

            $event->forceFill([
                'status' => $status,
                'error' => null,
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => WebhookEventStatus::Failed,
                'error' => mb_substr($exception->getMessage(), 0, 255),
            ])->save();

            throw $exception;
        }
    }
}
