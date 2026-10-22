<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminActionType;
use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\NewsletterSubscriber;
use App\Models\OutputTarget;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Auditoria: quem fez o que, em quem, quando e de onde (ADR-025).
 *
 * A tela e de leitura so. Nao existe edicao nem exclusao de linha de
 * auditoria - o valor dela esta exatamente em nao poder ser reescrita.
 *
 * O alvo aparece pelo nome (conta, plano, pagamento) quando ainda da para
 * chegar nele. Conta anonimizada nao tem mais nome nem e-mail util: nesse caso
 * vale o que a propria linha guardou em `meta`.
 */
class AuditController extends Controller
{
    private const PER_PAGE = 30;

    public function __invoke(Request $request): Response
    {
        $action = $request->string('action')->value();

        $actions = AdminAction::query()
            ->when(
                AdminActionType::tryFrom($action) !== null,
                fn (Builder $query) => $query->where('action', $action),
            )
            ->with('actor')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $labels = $this->labels($actions->getCollection());

        return Inertia::render('admin/Audit', [
            'entries' => $actions->through(fn (AdminAction $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action->value,
                'actor' => $entry->actor?->email,
                'target' => $labels[$entry->id] ?? null,
                'targetId' => $entry->target_id,
                'meta' => $entry->meta,
                'ip' => $entry->ip,
                'at' => Format::dateTime($entry->created_at),
            ]),
            'filters' => ['action' => $action],
            'actionTypes' => array_map(
                fn (AdminActionType $type): string => $type->value,
                AdminActionType::cases(),
            ),
            'total' => $actions->total(),
        ]);
    }

    /**
     * Nome do alvo por linha de auditoria, numa consulta por tipo - e nao uma
     * por linha.
     *
     * @param  Collection<int, AdminAction>  $entries
     * @return array<int, string|null>
     */
    private function labels(Collection $entries): array
    {
        $ids = [];

        foreach ($entries as $entry) {
            $key = (string) $entry->target_type;

            if ($entry->target_id !== null) {
                $ids[$key][] = $entry->target_id;
            }
        }

        $users = User::query()->whereIn('id', $ids[User::class] ?? [])->get()
            ->keyBy('id');
        $plans = Plan::query()->whereIn('id', $ids[Plan::class] ?? [])->get()
            ->keyBy('id');
        $subscribers = NewsletterSubscriber::query()
            ->whereIn('id', $ids[NewsletterSubscriber::class] ?? [])
            ->get()
            ->keyBy('id');
        $targets = OutputTarget::query()
            ->whereIn('id', $ids[OutputTarget::class] ?? [])
            ->get()
            ->keyBy('id');

        $labels = [];

        foreach ($entries as $entry) {
            $labels[$entry->id] = match ($entry->target_type) {
                User::class => $this->userLabel($users->get($entry->target_id), $entry),
                Plan::class => $plans->get($entry->target_id)?->name,
                NewsletterSubscriber::class => $subscribers->get($entry->target_id)?->email,
                OutputTarget::class => $targets->get($entry->target_id)?->label,
                Payment::class => $entry->target_id === null
                    ? null
                    : '#'.$entry->target_id,
                default => null,
            };
        }

        return $labels;
    }

    private function userLabel(?User $user, AdminAction $entry): ?string
    {
        // Conta anonimizada: o nome saiu e o e-mail da linha e um endereco
        // reservado. O que resta e o que a auditoria guardou na hora.
        if ($user === null || $user->anonymized_at !== null) {
            $email = $entry->meta['email'] ?? null;

            return is_string($email) && $email !== ''
                ? $email
                : ($entry->target_id === null ? null : '#'.$entry->target_id);
        }

        return $user->name.' <'.$user->email.'>';
    }
}
