<?php

namespace App\Models;

use App\Enums\WebhookEventStatus;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Notificacao crua do gateway (F6).
 *
 * Guarda o payload completo - auditoria, depuracao e reprocessamento - e nunca
 * e fonte de verdade: o recurso e sempre buscado na API antes de qualquer
 * acao. O unique `(gateway, event_id)` responde replay sem efeito novo.
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_id
 * @property string|null $topic
 * @property string|null $action
 * @property string|null $resource_id
 * @property string|null $request_id
 * @property WebhookEventStatus $status
 * @property int $attempts
 * @property array<string, mixed> $payload
 * @property string|null $error
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'gateway',
    'event_id',
    'topic',
    'action',
    'resource_id',
    'request_id',
    'status',
    'attempts',
    'payload',
    'error',
    'processed_at',
])]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WebhookEventStatus::class,
            'attempts' => 'integer',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
