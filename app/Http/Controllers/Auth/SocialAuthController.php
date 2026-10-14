<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

/**
 * Login social (Google e GitHub). Ver ADR-023.
 *
 * Rotas de maquina em /auth/{provider}/..., fora do prefixo de idioma: a URL
 * de callback e cadastrada uma vez no console do provedor (ADR-022).
 */
class SocialAuthController extends Controller
{
    /**
     * Manda o visitante para o provedor.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (! SocialProviders::isConfigured($provider)) {
            return $this->backWithStatus('auth.social.unavailable', $provider);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Volta do provedor: vincula a conta existente ou cria uma nova.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        if (! SocialProviders::isConfigured($provider)) {
            return $this->backWithStatus('auth.social.unavailable', $provider);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            // Estado invalido, codigo expirado, acesso negado no provedor...
            return $this->backWithStatus('auth.social.failed', $provider);
        }

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($account !== null) {
            // A suspensao vale tambem para quem entra pelo provedor: senao
            // suspender so fecharia uma das portas (ADR-025).
            if ($account->user->isSuspended()) {
                return $this->suspended();
            }

            return $this->authenticate($request, $account->user);
        }

        $email = $socialUser->getEmail();

        if (! is_string($email) || $email === '') {
            return $this->backWithStatus('auth.social.email_missing', $provider);
        }

        $verified = SocialProviders::emailIsVerified($provider, $socialUser);
        $user = User::query()->where('email', $email)->first();

        // Conta com esse e-mail so e vinculada quando o provedor confirma que
        // o e-mail e do usuario: sem isso, um provedor permissivo viraria
        // porta de entrada para tomar conta alheia (decisao do grill).
        if ($user !== null && ! $verified) {
            return $this->backWithStatus('auth.social.email_unverified', $provider);
        }

        if ($user !== null && $user->isSuspended()) {
            return $this->suspended();
        }

        if ($user === null) {
            // Conta sem senha: o fluxo de definir senha fica na tela de
            // confirmar senha (ADR-023).
            $user = User::query()->forceCreate([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $email,
                'email' => $email,
                'password' => null,
                'locale' => app()->getLocale(),
                'email_verified_at' => $verified ? now() : null,
            ]);
        } elseif ($user->email_verified_at === null && $verified) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
        ]);

        return $this->authenticate($request, $user);
    }

    private function authenticate(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Conta suspensa: mesma recusa do login por senha, no mesmo canal das
     * outras recusas do login social (ADR-025).
     */
    private function suspended(): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('status', __('auth.suspended'));
    }

    /**
     * Volta para o login com um aviso no idioma do visitante (a sessao guarda
     * o idioma resolvido na ida ao provedor).
     */
    private function backWithStatus(string $key, string $provider): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('status', trans($key, ['provider' => SocialProviders::label($provider)]));
    }
}
