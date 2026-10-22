<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WebhookEventStatus;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Eventos de webhook: o que o gateway mandou e como foi processado (F6).
 *
 * A tela e de leitura so, e de proposito: o corpo da notificacao nunca e fonte
 * de verdade - quem age e o job, que busca o recurso na API antes de qualquer
 * coisa (ADR-047). Reproduzir um evento por aqui seria uma segunda porta para
 * liberar acesso, e nao existe outra porta.
 *
 * O payload completo fica retido por 30 dias (`webhooks:prune`) e e o que o
 * admin ve quando precisa entender uma cobranca que nao fechou.
 */
class WebhookEventController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Os topicos que o processador conhece. Topico desconhecido continua na
     * lista como `ignored`, mas nao ganha item de filtro.
     *
     * @var list<string>
     */
    private const TOPICS = [
        'payment',
        'subscription_preapproval',
        'subscription_authorized_payment',
    ];

    public function __invoke(Request $request): Response
    {
        $search = $request->string('q')->trim()->value();
        $status = $request->string('status')->value();
        $topic = $request->string('topic')->value();

        $events = WebhookEvent::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('event_id', 'like', '%'.$search.'%')
                        ->orWhere('resource_id', 'like', '%'.$search.'%')
                        ->orWhere('request_id', 'like', '%'.$search.'%');
                });
            })
            ->when(
                WebhookEventStatus::tryFrom($status) !== null,
                fn (Builder $query) => $query->where('status', $status),
            )
            ->when(
                in_array($topic, self::TOPICS, true),
                fn (Builder $query) => $query->where('topic', $topic),
            )
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/WebhookEvents', [
            'events' => $events->through(fn (WebhookEvent $event): array => [
                'id' => $event->id,
                'gateway' => $event->gateway,
                'eventId' => $event->event_id,
                'topic' => $event->topic,
                'action' => $event->action,
                'resourceId' => $event->resource_id,
                'requestId' => $event->request_id,
                'status' => $event->status->value,
                'attempts' => $event->attempts,
                'error' => $event->error,
                'processedAt' => Format::dateTime($event->processed_at),
                'createdAt' => Format::dateTime($event->created_at),
                'payload' => $event->payload,
            ]),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'topic' => $topic,
            ],
            'topics' => self::TOPICS,
            'statuses' => array_map(
                fn (WebhookEventStatus $status): string => $status->value,
                WebhookEventStatus::cases(),
            ),
            'total' => $events->total(),
        ]);
    }
}
