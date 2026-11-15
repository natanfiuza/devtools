<?php

namespace App\Http\Requests\Colors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Escrita de comentario ou resposta (F7, ADR-139).
 *
 * O teto vem de `config/colors.php`; o filtro local e as regras de thread
 * ficam na action. `parent_id` e opcional e so aceita ULID.
 */
class StoreColorCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        if (is_string($body)) {
            $this->merge(['body' => trim($body)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => [
                'required',
                'string',
                'max:'.(int) config('colors.comments.body_max_length', 255),
            ],
            'parent_id' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('colors.validation.comment_body_required'),
            'body.max' => __('colors.validation.comment_body_max', [
                'max' => (int) config('colors.comments.body_max_length', 255),
            ]),
            'parent_id.ulid' => __('colors.validation.comment_parent_invalid'),
        ];
    }
}
