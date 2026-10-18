<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $user->fill($validated);

        // O idioma e preferencia da conta, e `locale` fica fora do Fillable: a
        // atribuicao e direta, como no cadastro (CreateNewUser).
        $escolhido = $validated['locale'] ?? null;
        $locale = is_string($escolhido) && Locales::isSupported($escolhido) ? $escolhido : null;

        if ($locale !== null) {
            $user->locale = $locale;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // O cookie junto: nas rotas sem prefixo ele vem antes da conta, entao e
        // ele que faz a interface seguir o idioma novo ja no proximo clique.
        if ($locale !== null) {
            Cookie::queue('locale', Locales::slug($locale), 60 * 24 * 365);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Agenda a exclusao da conta aplicando soft delete (janela de 30 dias).
     *
     * A conta entra em periodo de carencia de 30 dias. Se o usuario realizar
     * login durante esse prazo, a conta e reativada automaticamente. Apos 30
     * dias, o comando agendado accounts:purge-deleted executa o expurgo definitivo.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $user->delete();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', __('account.delete.scheduled_notice'));
    }
}
