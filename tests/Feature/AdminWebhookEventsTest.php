<?php

use App\Enums\WebhookEventStatus;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Eventos de webhook no painel (F6, ADR-047).
|
| A tela existe para explicar uma cobranca que nao fechou: mostra o payload
| cru como chegou e o resultado do processamento. Ela nao reprocessa nada - o
| corpo da notificacao nunca e fonte de verdade, e o job busca o recurso na API
| antes de agir.
*/

it('lista os eventos com o payload completo', function () {
    $admin = User::factory()->admin()->create();
    $event = WebhookEvent::factory()->processed()->create([
        'payload' => [
            'type' => 'payment',
            'data' => ['id' => '123'],
            'extra' => ['note' => 'cru'],
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.webhooks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/WebhookEvents')
            ->has('events.data', 1)
            ->where('events.data.0.eventId', $event->event_id)
            ->where('events.data.0.topic', 'payment')
            ->where('events.data.0.status', WebhookEventStatus::Processed->value)
            ->where('events.data.0.payload', [
                'type' => 'payment',
                'data' => ['id' => '123'],
                'extra' => ['note' => 'cru'],
            ])
            ->where('topics', [
                'payment',
                'subscription_preapproval',
                'subscription_authorized_payment',
            ]));
});

it('filtra por situacao, topico e identificador', function () {
    $admin = User::factory()->admin()->create();

    WebhookEvent::factory()->create(['event_id' => '111']);
    $falho = WebhookEvent::factory()->failed()->create([
        'event_id' => '222',
        'topic' => 'subscription_preapproval',
        'resource_id' => 'preap-9',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.webhooks.index', ['status' => 'failed', 'topic' => 'subscription_preapproval']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.eventId', $falho->event_id)
            ->where('events.data.0.error', 'gateway unavailable'));

    $this->actingAs($admin)
        ->get(route('admin.webhooks.index', ['q' => 'preap-9']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.resourceId', 'preap-9'));

    $this->actingAs($admin)
        ->get(route('admin.webhooks.index', ['q' => '222']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.eventId', '222'));
});

it('e somente leitura', function () {
    // Sem POST nem DELETE: reprocessar um evento por aqui seria uma segunda
    // porta para liberar acesso, e a unica porta e o job.
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.webhooks.index'))
        ->assertStatus(405);
});

it('recusa a tela para quem nao e admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.webhooks.index'))
        ->assertForbidden();
});
