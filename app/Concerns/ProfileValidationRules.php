<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\Locales;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Idioma da conta - so os que existem em lang/. `sometimes` porque a
     * preferencia e opcional: quem nao manda o campo nao muda de idioma.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function localeRules(): array
    {
        return ['sometimes', 'string', Rule::in(Locales::codes())];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Identificador publico da conta (`@nomedeusuario`, ADR-033).
     *
     * Apenas letras minusculas, digitos e underscore, de 3 a 30 caracteres.
     * No perfil e opcional (`nullable`); no formulario de intencao de
     * assinatura e obrigatorio (`$required = true`).
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function usernameRules(?int $userId = null, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'min:3',
            'max:30',
            'regex:/^[a-z0-9_]+$/',
            $userId === null
                ? Rule::unique(User::class, 'username')
                : Rule::unique(User::class, 'username')->ignore($userId),
        ];
    }
}
