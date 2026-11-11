<?php

namespace App\Http\Requests\Colors;

use App\Models\ColorPalette;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Exclusao definitiva de paleta (F4, ADR-123).
 *
 * Excluir e irreversivel, entao o dono precisa digitar o nome exato; a
 * comparacao e feita contra a paleta da rota, nunca contra um valor enviado
 * pelo cliente. Arquivar e o caminho reversivel.
 */
final class DeletePaletteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'confirm_name' => ['required', 'string', 'max:'.(int) config('colors.palettes.name_max_length', 80)],
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

                $palette = $this->route('colorPalette');

                if (! $palette instanceof ColorPalette) {
                    return;
                }

                if ($this->input('confirm_name') !== $palette->name) {
                    $validator->errors()->add('confirm_name', __('colors.validation.confirm_name'));
                }
            },
        ];
    }
}
