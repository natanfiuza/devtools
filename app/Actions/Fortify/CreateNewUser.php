<?php

namespace App\Actions\Fortify;

use App\Actions\Newsletter\SubscribeToNewsletter;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\NewsletterSource;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly SubscribeToNewsletter $subscribeToNewsletter) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'newsletter' => ['sometimes', 'boolean'],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // O idioma do formulario vira o idioma da conta: e por ele que saem os
        // e-mails e os acessos seguintes. "locale" nao esta em Fillable de
        // proposito (nao vem do request), entao a atribuicao e direta.
        $user->locale = app()->getLocale();
        $user->save();

        // Checkbox opcional, desmarcado por padrao: so inscreve se marcado, e
        // a inscricao ainda depende do clique no e-mail (double opt-in).
        if ((bool) filter_var($validated['newsletter'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            ($this->subscribeToNewsletter)($user->email, NewsletterSource::Register, $user->locale);
        }

        return $user;
    }
}
