<?php

namespace App\Http\Requests\BadgeForge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criacao de badge pessoal (F4, ADR-081).
 *
 * O PAT nunca volta no `_old_input` de um 422: `credential` esta em
 * `dontFlash` no `bootstrap/app.php`. A validacao de provider/params/private e
 * de credencial depende do catalogo e fica nas actions, com codigos
 * traduzidos.
 */
final class StoreBadgeAssetRequest extends FormRequest
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
        $maxParam = max(1, (int) config('badgeforge.providers.max_param_length', 200));

        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.personal_badges.name_max_length', 80),
                Rule::unique('badge_assets', 'name')->where('user_id', $this->user()?->getKey()),
            ],
            'type' => ['required', 'string', 'in:static,provider'],
            'form' => ['nullable', 'array'],
            'provider_slug' => [
                'required_if:type,provider',
                'nullable',
                'string',
                'max:40',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
            ],
            'definition_slug' => [
                'required_if:type,provider',
                'nullable',
                'string',
                'max:40',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
            ],
            'params' => ['nullable', 'array'],
            'params.*' => ['string', 'max:'.$maxParam],
            'private' => ['nullable', 'boolean'],
            'credential' => [
                'nullable',
                'string',
                'max:'.max(1, (int) config('badgeforge.personal_badges.credential_max_length', 512)),
            ],
            'credential_username' => ['nullable', 'string', 'max:80'],
        ];
    }
}
