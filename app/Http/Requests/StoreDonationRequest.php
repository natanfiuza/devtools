<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validacao do formulario de doacao (F6).
 *
 * O valor aceito e o do `config/donations.php` - a tela mostra os sugeridos,
 * mas o intervalo e quem manda. Nome e e-mail sao opcionais: doacao anonima e
 * valida e o recibo depende de o doador ter deixado e-mail.
 */
class StoreDonationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'email'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);

                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount_cents' => [
                'required',
                'integer',
                'min:'.(int) config('donations.min_cents'),
                'max:'.(int) config('donations.max_cents'),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
        ];
    }
}
