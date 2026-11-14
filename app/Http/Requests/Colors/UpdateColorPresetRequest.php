<?php

namespace App\Http\Requests\Colors;

use App\Models\ColorPreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renomeação de um preset do gerador (F5, ADR-093).
 *
 * A base é imutável: para mudar a cor, o usuário salva outro preset. O nome
 * segue único por conta, ignorando o próprio registro.
 */
class UpdateColorPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $preset = $this->route('colorPreset');

        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('colors.presets.name_max_length', 60),
                Rule::unique('color_presets', 'name')
                    ->where('user_id', $this->user()?->getKey())
                    ->ignore($preset instanceof ColorPreset ? $preset->getKey() : null),
            ],
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
        ];
    }
}
