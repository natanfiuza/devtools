<?php

namespace App\Http\Requests\Colors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edicao de comentario (F7, ADR-139): mesmo teto da criacao, sempre re-moderada.
 */
class UpdateColorCommentRequest extends FormRequest
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
        ];
    }
}
