<?php

namespace App\Http\Controllers;

use App\Actions\Payments\StartCheckout;
use App\Enums\CheckoutMode;
use App\Exceptions\Payments\CheckoutException;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Inicio e retorno do checkout online (`/subscribe`, F6).
 *
 * O POST abre a sessao e manda o navegador para o provedor. O GET de retorno e
 * apenas uma porta de volta: o navegador pode voltar de um pagamento aprovado,
 * negado ou abandonado, e nada disso confirma nada - quem confirma e o webhook.
 */
class CheckoutController extends Controller
{
    public function store(Request $request, StartCheckout $startCheckout): SymfonyResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'max:120'],
            'mode' => ['required', Rule::enum(CheckoutMode::class)],
        ]);

        /** @var User $user */
        $user = $request->user();

        $plan = Plan::query()
            ->where('slug', $validated['plan'])
            ->where('active', true)
            ->first();

        if ($plan === null) {
            return $this->refuse(CheckoutException::planUnavailable());
        }

        try {
            $session = $startCheckout($user, $plan, CheckoutMode::from($validated['mode']));
        } catch (CheckoutException $exception) {
            return $this->refuse($exception);
        } catch (PaymentGatewayException $exception) {
            // A excecao nao carrega corpo nem token, entao pode ir para o log.
            report($exception);

            return $this->refuseMessage('home.subscribe.checkout.errors.gateway');
        }

        $initPoint = $session->init_point;

        if (! is_string($initPoint) || $initPoint === '') {
            return $this->refuseMessage('home.subscribe.checkout.errors.gateway');
        }

        // `Inertia::location` responde 409 + `X-Inertia-Location` para o XHR do
        // Inertia e 302 para um POST de formulario comum.
        return Inertia::location($initPoint);
    }

    public function return(Request $request, CheckoutSession $checkoutSession): RedirectResponse
    {
        abort_unless($checkoutSession->user_id === $request->user()?->getKey(), 404);

        return redirect()->route('subscribe', [
            'plan' => $checkoutSession->plan->slug,
            'checkout' => $checkoutSession->ulid,
        ]);
    }

    private function refuse(CheckoutException $exception): RedirectResponse
    {
        return $this->refuseMessage($exception->messageKey);
    }

    private function refuseMessage(string $messageKey): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __($messageKey),
        ]);

        return back();
    }
}
