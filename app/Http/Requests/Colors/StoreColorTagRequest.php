<?php

namespace App\Http\Requests\Colors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Criacao de tag da conta (F4, ADR-103).
 *
 * A unicidade e por nome normalizado (`key`) dentro da conta; a colisao vira
 * erro de validacao em `name` vindo de `PaletteTags`.
 */
class StoreColorTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.(int) config('colors.tags.name_max_length', 30)],
        ];
    }
}
