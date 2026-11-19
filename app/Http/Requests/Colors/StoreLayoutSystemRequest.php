<?php

namespace App\Http\Requests\Colors;

use App\Models\LayoutSystem;
use App\Models\User;
use App\Support\Colors\LayoutSystemState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Payload de criacao/edicao de sistema de layout (F4, ADR-168).
 *
 * O `state` e validado estruturalmente por `LayoutSystemState` (snapshot da
 * F3 + entradas cruas + secundaria manual). O cliente envia apenas a FK da
 * paleta de origem; o credito e congelado no servidor.
 */
class StoreLayoutSystemRequest extends FormRequest
{
    /**
     * A posse e checada antes da validacao: sistema de outra conta responde
     * 404 (nunca 403, para nao confirmar que o ULID existe).
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $system = $this->route('layoutSystem');

        if (! $user instanceof User) {
            return false;
        }

        if ($system === null) {
            return true;
        }

        return $system instanceof LayoutSystem
            && (int) $system->user_id === (int) $user->getKey();
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }

    /**
     * O middleware `ConvertEmptyStringsToNull` transforma os textos vazios da
     * entrada em `null`. O estado persistido guarda string vazia para "campo
     * em branco", entao o payload e normalizado antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $state = $this->input('state');

        if (! is_array($state)) {
            return;
        }

        $inputs = $state['inputs'] ?? null;

        if (! is_array($inputs)) {
            return;
        }

        foreach (['seedText', 'backgroundText', 'secondaryText'] as $key) {
            if (($inputs[$key] ?? null) === null) {
                $inputs[$key] = '';
            }
        }

        $state['inputs'] = $inputs;

        $this->merge(['state' => $state]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.(int) config('colors.layout_systems.name_max_length', 80)],
            'description' => ['nullable', 'string', 'max:'.(int) config('colors.layout_systems.description_max_length', 500)],
            'visibility' => ['required', 'in:private,public'],
            'tags' => ['nullable', 'array', 'max:'.(int) config('colors.tags.per_layout_system', 10)],
            'tags.*' => ['string', 'max:'.(int) config('colors.tags.name_max_length', 30)],
            'state' => ['required', 'array'],
            'source_palette_id' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                (new LayoutSystemState)->validate($validator, $this->input('state'));
            },
        ];
    }
}
