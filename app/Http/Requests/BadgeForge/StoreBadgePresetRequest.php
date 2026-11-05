<?php

namespace App\Http\Requests\BadgeForge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBadgePresetRequest extends FormRequest
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
        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.presets.name_max_length', 80),
                Rule::unique('badge_presets', 'name')
                    ->where('user_id', $this->user()?->getKey()),
            ],
            'form' => ['required', 'array'],
        ];
    }
}
