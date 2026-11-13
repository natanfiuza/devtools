<?php

namespace App\Http\Requests\Colors;

use App\Support\Colors\ColorKey;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Favoritar ou desfavoritar uma cor da conta (F5, ADR-127).
 */
class ColorFavoriteRequest extends FormRequest
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
            'color' => ['required', 'string', 'regex:'.ColorKey::PATTERN],
        ];
    }
}
