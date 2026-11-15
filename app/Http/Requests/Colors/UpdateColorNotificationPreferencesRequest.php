<?php

namespace App\Http\Requests\Colors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Os quatro toggles de e-mail das notificacoes Colors (F7, ADR-142).
 *
 * O formulario envia o conjunto inteiro: preferencia ausente nao significa
 * "manter", significa "desligar", e o `updateOrCreate` grava os quatro de uma
 * vez. `database` nao tem toggle - o sino e sempre ligado.
 */
class UpdateColorNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email_on_like' => ['required', 'boolean'],
            'email_on_follow' => ['required', 'boolean'],
            'email_on_comment' => ['required', 'boolean'],
            'email_on_reply' => ['required', 'boolean'],
        ];
    }
}
