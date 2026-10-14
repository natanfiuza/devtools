<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminActionType;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Assinantes da newsletter (ADR-024).
 *
 * Duas acoes, e nenhuma delas apaga a linha:
 * - remover = descadastrar (`unsubscribed_at`), a mesma coisa que o link do
 *   e-mail faz. Apagar a linha permitiria a pessoa ser reinscrita amanha pelo
 *   formulario, sem nunca ter pedido - o descadastro tem que doer;
 * - reinscrever = desfazer o descadastro. Nao confirma ninguem por tabela: quem
 *   nunca clicou no link continua pendente, porque consentimento nao se
 *   inventa (ADR-024).
 */
class SubscriberController extends Controller
{
    use RecordsAdminActions;

    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->value();
        $status = $request->string('status')->value();

        $subscribers = $this->query($search, $status)
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/Subscribers', [
            'subscribers' => $subscribers->through(fn (NewsletterSubscriber $subscriber): array => [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'locale' => $subscriber->locale,
                'source' => $subscriber->source,
                'status' => $this->status($subscriber),
                'createdAt' => Format::date($subscriber->created_at),
            ]),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'total' => $subscribers->total(),
        ]);
    }

    /**
     * Baixa a lista filtrada em CSV. Sai tudo o que o filtro seleciona, e nao
     * so a pagina que esta na tela.
     */
    public function export(Request $request): StreamedResponse
    {
        $subscribers = $this->query(
            $request->string('q')->trim()->value(),
            $request->string('status')->value(),
        )->orderBy('id')->get();

        return response()->streamDownload(function () use ($subscribers): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['email', 'locale', 'source', 'status', 'created_at']);

            foreach ($subscribers as $subscriber) {
                fputcsv($out, [
                    $subscriber->email,
                    $subscriber->locale,
                    $subscriber->source,
                    $this->status($subscriber),
                    $subscriber->created_at?->toDateString(),
                ]);
            }

            fclose($out);
        }, 'newsletter-'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Desfaz o descadastro.
     */
    public function resubscribe(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        if ($subscriber->unsubscribed_at === null) {
            return $this->toast('info', __('admin.toasts.subscriber_already_subscribed'));
        }

        $subscriber->forceFill(['unsubscribed_at' => null])->save();

        $this->record($request, AdminActionType::SubscriberResubscribed, $subscriber);

        return $this->toast('success', __('admin.toasts.subscriber_resubscribed'));
    }

    /**
     * Descadastra (nao apaga a linha).
     */
    public function destroy(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        if ($subscriber->unsubscribed_at !== null) {
            return $this->toast('info', __('admin.toasts.subscriber_already_removed'));
        }

        $subscriber->forceFill(['unsubscribed_at' => now()])->save();

        $this->record($request, AdminActionType::SubscriberRemoved, $subscriber);

        return $this->toast('success', __('admin.toasts.subscriber_removed'));
    }

    private function status(NewsletterSubscriber $subscriber): string
    {
        return match (true) {
            $subscriber->unsubscribed_at !== null => 'unsubscribed',
            $subscriber->confirmed_at !== null => 'confirmed',
            default => 'pending',
        };
    }

    /**
     * @return Builder<NewsletterSubscriber>
     */
    private function query(string $search, string $status): Builder
    {
        return NewsletterSubscriber::query()
            ->when($search !== '', fn (Builder $query) => $query->where('email', 'like', '%'.$search.'%'))
            ->when($status === 'confirmed', fn (Builder $query) => $query
                ->whereNotNull('confirmed_at')
                ->whereNull('unsubscribed_at'))
            ->when($status === 'pending', fn (Builder $query) => $query
                ->whereNull('confirmed_at')
                ->whereNull('unsubscribed_at'))
            ->when($status === 'unsubscribed', fn (Builder $query) => $query->whereNotNull('unsubscribed_at'));
    }

    private function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
