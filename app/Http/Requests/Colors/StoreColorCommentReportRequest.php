<?php

namespace App\Http\Requests\Colors;

use App\Enums\ColorCommentReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Denúncia de comentário (F7, ADR-141).
 *
 * O motivo é uma lista fechada: nenhum texto livre entra aqui.
 */
class StoreColorCommentReportRequest extends FormRequest
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
        return [
            'reason' => ['required', Rule::enum(ColorCommentReportReason::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('colors.validation.report_reason_invalid'),
            'reason.enum' => __('colors.validation.report_reason_invalid'),
        ];
    }
}
