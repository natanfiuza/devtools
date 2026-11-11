<?php

namespace App\Http\Requests\Colors;

use App\Support\Colors\PaletteRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Duplicacao com nova base (F4, ADR-124).
 *
 * O corpo e opcional: sem ele, a copia usa o retrato da origem. Com ele, a
 * pagina publica envia a escala recalculada localmente (sempre 11 tons), os
 * papeis e, opcionalmente, tags. Nada aqui e persistido na origem.
 */
final class DuplicatePaletteRequest extends FormRequest
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
        $colors = (new PaletteRules)->scaleColorsRules();
        $colors['colors'] = ['sometimes', 'array:base,items'];
        $colors['colors.base'] = ['required_with:colors', 'string', 'regex:'.PaletteRules::HEX];
        $colors['colors.items'] = ['required_with:colors', 'array', 'size:'.count(PaletteRules::TONES)];

        return array_merge($colors, $this->roleRules(), [
            'tags' => ['nullable', 'array', 'max:'.(int) config('colors.tags.per_palette', 10)],
            'tags.*' => ['string', 'max:'.(int) config('colors.tags.name_max_length', 30)],
        ]);
    }

    /**
     * Os papeis so sao exigidos quando ha uma nova base; as folhas usam
     * `required_with:roles` para nao obrigarem um body vazio a declara-las.
     *
     * @return array<string, mixed>
     */
    private function roleRules(): array
    {
        $rules = [
            'roles' => ['required_with:colors', 'array:light,dark'],
        ];

        foreach (['light', 'dark'] as $theme) {
            $rules['roles.'.$theme] = ['required_with:roles', 'array:'.implode(',', PaletteRules::ROLES)];

            foreach (PaletteRules::ROLES as $role) {
                $rules['roles.'.$theme.'.'.$role] = ['required_with:roles', 'string', 'regex:'.PaletteRules::HEX];
            }
        }

        return $rules;
    }
}
