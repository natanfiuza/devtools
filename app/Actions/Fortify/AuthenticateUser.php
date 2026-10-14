<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login por e-mail e senha, no lugar do `Auth::attempt` padrao do Fortify.
 *
 * Existe por causa da suspensao: `Auth::attempt` so sabe dizer sim ou nao, e a
 * conta suspensa precisa de um "nao" com texto proprio - dizer "credenciais
 * invalidas" para quem digita a senha certa manda a pessoa tentar de novo para
 * sempre (ADR-025).
 *
 * O Fortify valida o formulario antes de chamar isto, entao aqui se responde
 * apenas "autentica" ou a excecao que explica por que nao.
 */
final class AuthenticateUser
{
    public function __invoke(Request $request): ?User
    {
        $email = $request->string('email')->trim()->lower()->value();
        $password = $request->string('password')->value();

        $user = User::query()->where('email', $email)->first();

        // Sem conta, sem senha local ou senha errada: a mesma resposta, para
        // nao revelar quais e-mails existem.
        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => __('auth.suspended'),
            ]);
        }

        return $user;
    }
}
