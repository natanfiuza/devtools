<?php

namespace App\Http\Requests\BadgeForge;

use App\Models\BadgeAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBadgeAssetRequest extends FormRequest
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
        $asset = $this->route('badgeAsset');
        $ignore = $asset instanceof BadgeAsset ? $asset->getKey() : null;

        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.personal_badges.name_max_length', 80),
                Rule::unique('badge_assets', 'name')
                    ->where('user_id', $this->user()?->getKey())
                    ->ignore($ignore),
            ],
        ];
    }
}
