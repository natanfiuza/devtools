<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * O toggle do historico e booleano e obrigatorio: sem o campo, um formulario
 * quebrado desligaria a gravacao sem a pessoa ter pedido.
 */
class UpdateHistoryPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'history_enabled' => ['required', 'boolean'],
        ];
    }
}
