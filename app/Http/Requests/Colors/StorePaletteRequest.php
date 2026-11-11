<?php

namespace App\Http\Requests\Colors;

use App\Enums\PaletteKind;
use App\Support\Colors\PaletteRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Payload de criacao/edicao de paleta (F4, ADR-123).
 *
 * O formato e validado por tipo: escala exige os 11 tons, extraida aceita
 * apenas 5/7/9/11 e a base precisa estar entre as cores. A versao do
 * algoritmo gravada e a que o cliente declarou; se divergir da configuracao,
 * a paleta nao e criada.
 */
class StorePaletteRequest extends FormRequest
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
        $kind = (string) $this->input('kind', PaletteKind::Scale->value);
        $rules = new PaletteRules;

        $colors = $kind === PaletteKind::Extracted->value
            ? $rules->extractedColorsRules()
            : $rules->scaleColorsRules();

        return array_merge([
            'name' => ['required', 'string', 'max:'.(int) config('colors.palettes.name_max_length', 80)],
            'description' => ['nullable', 'string', 'max:'.(int) config('colors.palettes.description_max_length', 500)],
            'visibility' => ['required', 'in:private,public'],
            'kind' => ['required', 'in:scale,extracted'],
            'algorithm_version' => ['required', 'integer', 'min:1'],
            'tags' => ['nullable', 'array', 'max:'.(int) config('colors.tags.per_palette', 10)],
            'tags.*' => ['string', 'max:'.(int) config('colors.tags.name_max_length', 30)],
        ], $colors, $rules->rolesRules(), $rules->sourceRules());
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

                $kind = PaletteKind::from((string) $this->input('kind'));
                $this->validateKind($validator, $kind);
            },
        ];
    }

    private function validateKind(Validator $validator, PaletteKind $kind): void
    {
        $rules = new PaletteRules;
        $expected = $kind === PaletteKind::Scale
            ? (int) config('colors.algorithm_versions.scale', 1)
            : (int) config('colors.algorithm_versions.extraction', 1);

        if ((int) $this->input('algorithm_version') !== $expected) {
            $validator->errors()->add('algorithm_version', __('colors.validation.algorithm_version'));
        }

        /** @var array{base: string, items: list<array{hex: string, tone?: int}>} $colors */
        $colors = $this->input('colors');

        if ($kind === PaletteKind::Scale) {
            if ($this->input('source') !== null) {
                $validator->errors()->add('source', __('colors.validation.source_not_allowed'));
            }

            return;
        }

        if (! $rules->validExtractedCount($colors['items'])) {
            $validator->errors()->add('colors.items', __('colors.validation.extracted_count'));
        }

        $hexes = array_map(
            static fn (array $item): string => strtolower($item['hex']),
            $colors['items'],
        );

        if (! in_array(strtolower($colors['base']), $hexes, true)) {
            $validator->errors()->add('colors.base', __('colors.validation.base_not_in_items'));
        }
    }
}
