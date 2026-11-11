<?php

namespace App\Http\Requests\Colors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reordenacao das tags da conta (F4, ADR-103).
 *
 * A lista enviada e a ordem final; ids de outra conta sao recusados com 404
 * em `PaletteTags::reorder` antes de qualquer escrita.
 */
final class ReorderColorTagsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'string', 'distinct'],
        ];
    }
}
