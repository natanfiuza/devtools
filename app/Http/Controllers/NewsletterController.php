<?php

namespace App\Http\Controllers;

use App\Actions\Newsletter\SubscribeToNewsletter;
use App\Enums\NewsletterSource;
use App\Http\Requests\Newsletter\SubscribeRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Newsletter com double opt-in (ADR-024).
 *
 * Confirmar e descadastar sao duas etapas: o GET mostra a pagina com o botao e
 * o POST aplica a mudanca. Assim o prefetch do navegador (ou o scanner do
 * provedor de e-mail) nao confirma nem descadastra ninguem sozinho.
 */
class NewsletterController extends Controller
{
    /**
     * Inscricao pelo rodape do site.
     */
    public function subscribe(SubscribeRequest $request, SubscribeToNewsletter $subscribe): RedirectResponse
    {
        $sent = $subscribe($request->string('email')->value(), NewsletterSource::Footer);

        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => __('newsletter.subscribe.check_email')]
            : ['type' => 'info', 'message' => __('newsletter.subscribe.already_confirmed')]);

        return back();
    }

    /**
     * Pagina do link de confirmacao (nao confirma nada por si so).
     */
    public function confirm(string $locale, string $token): Response
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->first();

        return Inertia::render('newsletter/Confirm', [
            'token' => $token,
            'state' => match (true) {
                $subscriber === null => 'invalid',
                $subscriber->isConfirmed() => 'confirmed',
                default => 'pending',
            },
        ]);
    }

    /**
     * Confirma a inscricao.
     */
    public function confirmStore(string $locale, string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->first();

        if ($subscriber === null) {
            return $this->invalidLink();
        }

        if (! $subscriber->isConfirmed()) {
            $subscriber->forceFill(['confirmed_at' => now()])->save();
        }

        return redirect()->route('newsletter.confirm', ['locale' => $locale, 'token' => $token]);
    }

    /**
     * Pagina do link de descadastro (nao descadastra nada por si so).
     */
    public function unsubscribe(string $locale, string $token): Response
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->first();

        return Inertia::render('newsletter/Unsubscribe', [
            'token' => $token,
            'state' => match (true) {
                $subscriber === null => 'invalid',
                $subscriber->unsubscribed_at !== null => 'unsubscribed',
                default => 'pending',
            },
        ]);
    }

    /**
     * Descadastra.
     */
    public function unsubscribeStore(string $locale, string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->first();

        if ($subscriber === null) {
            return $this->invalidLink();
        }

        if ($subscriber->unsubscribed_at === null) {
            $subscriber->forceFill(['unsubscribed_at' => now()])->save();
        }

        return redirect()->route('newsletter.unsubscribe', ['locale' => $locale, 'token' => $token]);
    }

    /**
     * Token que nao existe mais: volta para a home com aviso, em vez de uma
     * tela de erro.
     */
    private function invalidLink(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('newsletter.invalid_link'),
        ]);

        return redirect()->route('home', ['locale' => app()->getLocale()]);
    }
}
