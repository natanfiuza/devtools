<?php

namespace App\Http\Requests\Colors;

use App\Models\LayoutSystem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Exclusao definitiva de sistema de layout (F4, ADR-164).
 *
 * Excluir e irreversivel, entao o dono digita o nome exato; a comparacao e
 * feita contra o sistema da rota, nunca contra um valor enviado pelo cliente.
 */
final class DeleteLayoutSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $system = $this->route('layoutSystem');

        return $user instanceof User
            && $system instanceof LayoutSystem
            && (int) $system->user_id === (int) $user->getKey();
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'confirm_name' => ['required', 'string', 'max:'.(int) config('colors.layout_systems.name_max_length', 80)],
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

                $system = $this->route('layoutSystem');

                if (! $system instanceof LayoutSystem) {
                    return;
                }

                if ($this->input('confirm_name') !== $system->name) {
                    $validator->errors()->add('confirm_name', __('colors.systems.validation.confirm_name'));
                }
            },
        ];
    }
}
