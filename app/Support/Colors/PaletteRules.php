<?php

namespace App\Support\Colors;

/**
 * Regras de validacao do payload de paleta (F4, ADR-123).
 *
 * O servidor valida o formato, nao recalcula as cores: o que o navegador
 * salvou e exatamente o que sera servido de volta. Escala exige os 11 tons
 * conhecidos; extraida aceita 5, 7, 9 ou 11 cores na ordem de peso.
 */
final class PaletteRules
{
    public const HEX = '/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/';

    public const TONES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    public const EXTRACTED_COUNTS = [5, 7, 9, 11];

    public const ROLES = [
        'primary',
        'secondary',
        'accent',
        'background',
        'surface',
        'text',
        'text_muted',
        'border',
    ];

    /**
     * @return array<string, list<string>>
     */
    public function scaleColorsRules(): array
    {
        return [
            'colors' => ['required', 'array:base,items'],
            'colors.base' => ['required', 'string', 'regex:'.self::HEX],
            'colors.items' => ['required', 'array', 'size:'.count(self::TONES)],
            'colors.items.*.hex' => ['required', 'string', 'regex:'.self::HEX],
            'colors.items.*.tone' => ['required', 'integer', 'in:'.implode(',', self::TONES)],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function extractedColorsRules(): array
    {
        return [
            'colors' => ['required', 'array:base,items'],
            'colors.base' => ['required', 'string', 'regex:'.self::HEX],
            'colors.items' => ['required', 'array', 'min:5', 'max:11'],
            'colors.items.*.hex' => ['required', 'string', 'regex:'.self::HEX],
            'colors.items.*.tone' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function rolesRules(): array
    {
        $rules = [
            'roles' => ['required', 'array:light,dark'],
        ];

        foreach (['light', 'dark'] as $theme) {
            $rules['roles.'.$theme] = ['required', 'array:'.implode(',', self::ROLES)];

            foreach (self::ROLES as $role) {
                $rules['roles.'.$theme.'.'.$role] = ['required', 'string', 'regex:'.self::HEX];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, list<string>>
     */
    public function sourceRules(): array
    {
        return [
            'source' => ['nullable', 'array:kind,label,host'],
            'source.kind' => ['required_with:source', 'in:file,url'],
            'source.label' => ['required_if:source.kind,file', 'nullable', 'string', 'max:80'],
            'source.host' => ['required_if:source.kind,url', 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @param  list<array{hex: string, tone?: int}>  $items
     */
    public function validExtractedCount(array $items): bool
    {
        return in_array(count($items), self::EXTRACTED_COUNTS, true);
    }
}
