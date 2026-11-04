<?php

namespace App\Http\Requests\BadgeForge;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Troca do PAT de uma badge pessoal (F4, ADR-081).
 *
 * O valor e write-only: nunca e devolvido pelas props e nunca vai para o
 * `_old_input` da sessao (`dontFlash` em `bootstrap/app.php`).
 */
final class SetBadgeAssetCredentialRequest extends FormRequest
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
            'credential' => [
                'required',
                'string',
                'max:'.max(1, (int) config('badgeforge.personal_badges.credential_max_length', 512)),
            ],
            'credential_username' => ['nullable', 'string', 'max:80'],
        ];
    }
}
