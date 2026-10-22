<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nome do token de API.
 *
 * O nome e a unica forma de o dono reconhecer a integracao na lista, entao nao
 * pode repetir na mesma conta. O limite de 80 e o mesmo da coluna do Sanctum.
 */
class StoreApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $tokenableId = $user instanceof User ? $user->getKey() : null;
        $tokenableType = $user instanceof User ? $user->getMorphClass() : null;

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('personal_access_tokens', 'name')
                    ->where('tokenable_id', $tokenableId)
                    ->where('tokenable_type', $tokenableType),
            ],
        ];
    }
}
