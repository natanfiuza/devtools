<?php

namespace App\Http\Requests\BadgeForge;

use App\Models\BadgeCustomLogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCustomLogoRequest extends FormRequest
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
        $logo = $this->route('badgeCustomLogo');
        $ignore = $logo instanceof BadgeCustomLogo ? $logo->getKey() : null;

        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.custom_logos.name_max_length', 80),
                Rule::unique('badge_custom_logos', 'name')
                    ->where('user_id', $this->user()?->getKey())
                    ->ignore($ignore),
            ],
        ];
    }
}
