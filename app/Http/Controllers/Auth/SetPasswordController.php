<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Define a primeira senha de uma conta criada por login social (ver ADR-023).
 *
 * O caminho ate aqui e o proprio Fortify: a area sensivel manda para a tela de
 * confirmar senha, e quem nao tem senha ve nela o formulario de definir.
 */
class SetPasswordController extends Controller
{
    /**
     * Grava a senha e libera a area sensivel.
     */
    public function store(SetPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Quem ja tem senha troca em settings/security, informando a atual.
        if (! $user instanceof User || $user->hasPassword()) {
            abort(403);
        }

        // Senha em texto puro: o cast "hashed" do modelo cuida do hash, igual
        // ao resto do app (ver ResetUserPassword).
        $user->forceFill([
            'password' => $request->string('password')->value(),
        ])->save();

        // A senha acabou de ser definida aqui dentro, entao a area sensivel ja
        // esta liberada - mesma chave que o Fortify usa ao confirmar a senha.
        $request->session()->put('auth.password_confirmed_at', time());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('auth.confirm.set_success'),
        ]);

        return redirect()->intended(route('security.edit'));
    }
}
