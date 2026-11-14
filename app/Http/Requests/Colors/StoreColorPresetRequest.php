<?php

namespace App\Http\Requests\Colors;

use App\Support\Colors\PaletteRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação de um preset do gerador (F5, ADR-093).
 *
 * O nome é único por conta; a base aceita HEX6/HEX8 e é gravada em
 * minúsculas. O preset guarda a entrada, não a escala derivada.
 */
class StoreColorPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $base = $this->input('base');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }

        if (is_string($base)) {
            $this->merge(['base' => strtolower(trim($base))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('colors.presets.name_max_length', 60),
                Rule::unique('color_presets', 'name')
                    ->where('user_id', $this->user()?->getKey()),
            ],
            'base' => ['required', 'string', 'regex:'.PaletteRules::HEX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('colors.validation.preset_name_required'),
            'name.max' => __('colors.validation.preset_name_max', [
                'max' => (int) config('colors.presets.name_max_length', 60),
            ]),
            'name.unique' => __('colors.validation.preset_name_taken'),
            'base.required' => __('colors.validation.preset_base_required'),
            'base.regex' => __('colors.validation.preset_base_invalid'),
        ];
    }
}
