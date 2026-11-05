<?php

namespace App\Http\Requests\BadgeForge;

use App\Enums\CustomMetricFormatter;
use App\Models\BadgeCustomProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edicao de provider customizado (F7, ADR-087).
 *
 * Template e slugs sao imutaveis: `url_template` e proibido e o conjunto de
 * slugs precisa ser exatamente o existente (a action confere). O que muda e
 * nome, label, pointer e formatter.
 */
final class UpdateBadgeCustomProviderRequest extends FormRequest
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
        $provider = $this->route('badgeCustomProvider');
        $ignore = $provider instanceof BadgeCustomProvider ? $provider->getKey() : null;

        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.custom_providers.name_max_length', 80),
                Rule::unique('badge_custom_providers', 'name')
                    ->where('user_id', $this->user()?->getKey())
                    ->ignore($ignore),
            ],
            'url_template' => ['prohibited'],
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
                'max:'.(int) config('badgeforge.custom_providers.slug_max_length', 32),
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
