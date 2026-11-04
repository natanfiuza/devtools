<?php

namespace App\Http\Requests\BadgeForge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criacao de endpoint JSON (F5, ADR-083).
 *
 * `url` fica em `dontFlash` no `bootstrap/app.php`: a origem e dado sensivel e
 * nunca volta no `_old_input` de um 422. As regras de SSRF ficam na action,
 * porque dependem do guard e do DNS.
 */
final class StoreBadgeEndpointRequest extends FormRequest
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
                'max:'.(int) config('badgeforge.endpoint.name_max_length', 80),
                Rule::unique('badge_endpoints', 'name')->where('user_id', $this->user()?->getKey()),
            ],
            'url' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.endpoint.url_max_length', 2048),
            ],
        ];
    }
}
