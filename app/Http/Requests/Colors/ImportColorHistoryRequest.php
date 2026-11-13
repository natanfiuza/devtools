<?php

namespace App\Http\Requests\Colors;

use App\Support\Colors\ColorKey;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Importacao explicita do historico local para a conta (F5, ADR-132).
 *
 * O lote e limitado por configuracao; a importacao so acontece por pedido do
 * usuario, nunca automaticamente no login.
 */
class ImportColorHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $colors = $this->input('colors');

        if (! is_array($colors)) {
            return;
        }

        $this->merge([
            'colors' => array_map(
                static fn (mixed $color): mixed => is_string($color) ? ColorKey::normalize($color) : $color,
                $colors,
            ),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'colors' => ['required', 'array', 'min:1', 'max:'.(int) config('colors.library.import_batch', 200)],
            'colors.*' => ['required', 'string', 'regex:'.ColorKey::PATTERN],
        ];
    }
}
