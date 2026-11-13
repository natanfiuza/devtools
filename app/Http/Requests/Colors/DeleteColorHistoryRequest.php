<?php

namespace App\Http\Requests\Colors;

use App\Support\Colors\ColorKey;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Remocao de uma cor ou de todo o historico da conta (F5, ADR-127).
 *
 * Sem `color` no corpo, o historico inteiro e limpo. A cor viaja no JSON
 * porque `#` nao e um segmento de URL seguro.
 */
class DeleteColorHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $color = $this->input('color');

        if (is_string($color)) {
            $this->merge(['color' => ColorKey::normalize($color)]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'color' => ['nullable', 'string', 'regex:'.ColorKey::PATTERN],
        ];
    }
}
