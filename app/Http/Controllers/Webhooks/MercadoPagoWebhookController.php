<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Support\Payments\MercadoPagoSignature;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe as notificacoes da Mercado Pago (F6).
 *
 * A resposta e curta de proposito: verificar a assinatura, guardar o payload
 * cru e enfileirar. O provedor reenvia o que nao responde 2xx, entao qualquer
 * chamada a API dele acontece no job, nunca aqui.
 *
 * O corpo nao e fonte de verdade: so o `data.id` e usado para achar o recurso
 * que o job vai buscar na API antes de agir.
 */
final class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = trim((string) config('payments.mercadopago.webhook_secret'));

        if ($secret === '') {
            return response()->json(['status' => 'unavailable'], 503);
        }

        $payload = $request->all();
        $dataId = $this->text(data_get($payload, 'data.id')) ?? $this->text($request->query('data.id'));
        $requestId = trim((string) $request->header('x-request-id', ''));
        $signature = (string) $request->header('x-signature', '');

        if ($dataId === null || ! MercadoPagoSignature::isValid($secret, $signature, $requestId, $dataId)) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $topic = $this->text(data_get($payload, 'type'))
            ?? $this->text(data_get($payload, 'topic'))
            ?? $this->text($request->query('topic'));
        $action = $this->text(data_get($payload, 'action'));
        $eventId = $this->text(data_get($payload, 'id'))
            ?? hash('sha256', implode('|', [$topic ?? '', $dataId, $requestId]));

        try {
            $event = WebhookEvent::create([
                'gateway' => 'mercadopago',
                'event_id' => mb_substr($eventId, 0, 64),
                'topic' => $topic !== null ? mb_substr($topic, 0, 48) : null,
                'action' => $action !== null ? mb_substr($action, 0, 64) : null,
                'resource_id' => mb_substr($dataId, 0, 64),
                'request_id' => $requestId !== '' ? mb_substr($requestId, 0, 64) : null,
                'status' => 'pending',
                'attempts' => 0,
                'payload' => $payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Replay do provedor: ja esta guardado e ja tem job.
            return response()->json(['status' => 'duplicate']);
        }

        ProcessWebhookEvent::dispatch((int) $event->getKey())->afterCommit();

        return response()->json(['status' => 'accepted'], 201);
    }

    private function text(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return is_int($value) ? (string) $value : null;
    }
}
