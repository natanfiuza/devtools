<?php

namespace App\Http\Requests\BadgeForge;

use App\Models\BadgeEndpoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renomeacao de endpoint JSON (F5, ADR-083).
 *
 * A URL de origem e imutavel: troca-la mudaria a identidade publicada, entao
 * o caminho e excluir e criar de novo.
 */
final class UpdateBadgeEndpointRequest extends FormRequest
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
        $endpoint = $this->route('badgeEndpoint');
        $ignore = $endpoint instanceof BadgeEndpoint ? $endpoint->getKey() : null;

        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.endpoint.name_max_length', 80),
                Rule::unique('badge_endpoints', 'name')
                    ->where('user_id', $this->user()?->getKey())
                    ->ignore($ignore),
            ],
        ];
    }
}
