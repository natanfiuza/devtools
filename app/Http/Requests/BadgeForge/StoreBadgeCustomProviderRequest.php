<?php

namespace App\Http\Requests\BadgeForge;

use App\Enums\CustomMetricFormatter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criacao de provider customizado (F7, ADR-087).
 *
 * Shape basico aqui; template, placeholders, path de exemplo e JSON Pointers
 * dependem do guard/probe e ficam na action. O template e sensivel e esta em
 * `dontFlash` (`bootstrap/app.php`): ele nunca volta no `_old_input`.
 */
final class StoreBadgeCustomProviderRequest extends FormRequest
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
        $slugMax = (int) config('badgeforge.custom_providers.slug_max_length', 32);

        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.custom_providers.name_max_length', 80),
                Rule::unique('badge_custom_providers', 'name')->where('user_id', $this->user()?->getKey()),
            ],
            'url_template' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.custom_providers.url_max_length', 2048),
            ],
            'path_params' => [
                'required',
                'array',
                'min:1',
                'max:'.(int) config('badgeforge.custom_providers.max_path_params', 5),
            ],
            'path_params.*' => [
                'required',
                'string',
                'distinct',
                'regex:/\A[A-Za-z][A-Za-z0-9_]{0,31}\z/',
            ],
            'example_path' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.custom_providers.example_path_max_length', 512),
            ],
            'metrics' => [
                'required',
                'array',
                'min:1',
                'max:'.(int) config('badgeforge.custom_providers.max_metrics', 10),
            ],
            'metrics.*.slug' => [
                'required',
                'string',
                'distinct',
                'max:'.$slugMax,
                'regex:/\A[a-z0-9][a-z0-9-]*\z/',
            ],
            'metrics.*.label' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.custom_providers.label_max_length', 80),
            ],
            'metrics.*.pointer' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.custom_providers.pointer_max_length', 256),
            ],
            'metrics.*.formatter' => [
                'required',
                Rule::enum(CustomMetricFormatter::class),
            ],
        ];
    }
}
