<?php

use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'payments.mercadopago.access_token' => 'token-de-teste',
        'payments.mercadopago.webhook_secret' => 'segredo-de-teste',
    ]);
});

/**
 * Assinatura no formato `ts=<ts>,v1=<hmac>` sobre o manifesto da MP.
 */
function assinaturaWebhook(string $dataId, string $requestId = 'req-123', string $ts = '1700000000'): string
{
    $manifest = 'id:'.mb_strtolower($dataId).';request-id:'.$requestId.';ts:'.$ts.';';

    return 'ts='.$ts.',v1='.hash_hmac('sha256', $manifest, 'segredo-de-teste');
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, string>
 */
function cabecalhosWebhook(array $payload, ?string $assinatura = null, string $requestId = 'req-123'): array
{
    return [
        'x-signature' => $assinatura ?? assinaturaWebhook((string) data_get($payload, 'data.id'), $requestId),
        'x-request-id' => $requestId,
    ];
}

/**
 * @return array<string, mixed>
 */
function payloadWebhook(string $topic = 'payment', string $id = '123456', string $notificationId = '900001'): array
{
    return [
        'id' => $notificationId,
        'live_mode' => false,
        'type' => $topic,
        'action' => $topic.'.updated',
        'date_created' => now()->toIso8601String(),
        'data' => ['id' => $id],
    ];
}

it('responde 503 sem segredo configurado', function () {
    config(['payments.mercadopago.webhook_secret' => null]);

    $payload = payloadWebhook();

    $this->postJson('/webhooks/mercadopago', $payload, cabecalhosWebhook($payload))
        ->assertStatus(503);

    expect(WebhookEvent::query()->count())->toBe(0);
});

it('recusa assinatura ausente ou invalida sem guardar o evento', function (string $situacao) {
    Queue::fake();

    $payload = payloadWebhook();

    $headers = $situacao === 'ausente'
        ? ['x-request-id' => 'req-123']
        : cabecalhosWebhook($payload, 'ts=1700000000,v1=assinatura-falsa');

    $this->postJson('/webhooks/mercadopago', $payload, $headers)
        ->assertStatus(401);

    expect(WebhookEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with(['ausente', 'invalida']);

it('guarda o payload completo e enfileira o processamento', function () {
    Queue::fake();

    $payload = payloadWebhook('payment', '987654', '900123');

    $this->postJson('/webhooks/mercadopago', $payload, cabecalhosWebhook($payload))
        ->assertStatus(201)
        ->assertJson(['status' => 'accepted']);

    $event = WebhookEvent::query()->sole();

    expect($event->gateway)->toBe('mercadopago')
        ->and($event->event_id)->toBe('900123')
        ->and($event->topic)->toBe('payment')
        ->and($event->action)->toBe('payment.updated')
        ->and($event->resource_id)->toBe('987654')
        ->and($event->request_id)->toBe('req-123')
        ->and($event->payload)->toBe($payload);

    Queue::assertPushed(ProcessWebhookEvent::class, 1);
});

it('responde replay do mesmo evento sem criar job novo', function () {
    Queue::fake();

    $payload = payloadWebhook();

    $this->postJson('/webhooks/mercadopago', $payload, cabecalhosWebhook($payload))
        ->assertStatus(201);

    $this->postJson('/webhooks/mercadopago', $payload, cabecalhosWebhook($payload))
        ->assertStatus(200)
        ->assertJson(['status' => 'duplicate']);

    expect(WebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessWebhookEvent::class, 1);
});

it('aceita notificacao do provedor sem sessao de conta', function () {
    Queue::fake();

    $payload = payloadWebhook();

    expect(auth()->check())->toBeFalse();

    $this->postJson('/webhooks/mercadopago', $payload, cabecalhosWebhook($payload))
        ->assertStatus(201);
});

it('normaliza data.id alfanumerico em minusculas na assinatura', function () {
    Queue::fake();

    $payload = payloadWebhook('payment', 'AbC123xyz', '900777');

    $this->postJson('/webhooks/mercadopago', $payload, cabecalhosWebhook($payload))
        ->assertStatus(201);

    expect(WebhookEvent::query()->sole()->resource_id)->toBe('AbC123xyz');
});
