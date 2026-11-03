<?php

namespace App\Http\Requests\BadgeForge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Upload de logo personalizado (ADR-078).
 *
 * O limite de tamanho e os nomes vem da configuracao; o conteudo do arquivo
 * nao e validado aqui - `CustomLogoSvgParser` e a autoridade e devolve um 422
 * traduzido por codigo.
 */
final class StoreCustomLogoRequest extends FormRequest
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
        $maxBytes = max(1, (int) config('badgeforge.custom_logos.max_bytes', 262144));

        return [
            'name' => [
                'required',
                'string',
                'max:'.(int) config('badgeforge.custom_logos.name_max_length', 80),
                Rule::unique('badge_custom_logos', 'name')
                    ->where('user_id', $this->user()?->getKey()),
            ],
            'logo' => ['required', 'file', 'max:'.max(1, intdiv($maxBytes, 1024))],
        ];
    }
}
