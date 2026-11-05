<?php

namespace App\Http\Requests\BadgeForge;

use App\Models\BadgePreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBadgePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $preset = $this->route('badgePreset');
        $ignore = $preset instanceof BadgePreset ? $preset->getKey() : null;

        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.presets.name_max_length', 80),
                Rule::unique('badge_presets', 'name')
                    ->where('user_id', $this->user()?->getKey())
                    ->ignore($ignore),
            ],
        ];
    }
}
