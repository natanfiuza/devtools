<?php

namespace App\Http\Requests;

use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Models\OutputTarget;
use App\Support\Locales;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pedido de geracao de query.
 *
 * Os limites daqui sao os tetos tecnicos do servidor e existem para recusar
 * payload absurdo antes de canonicalizar. O limite comercial do plano e checado
 * na acao, que conhece a cota e a unidade (bytes e caracteres).
 */
class StoreAiQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSchemaBytes = max(1, (int) config('ai.limits.max_schema_bytes', 65536));
        $maxDescriptionChars = max(1, (int) config('ai.limits.max_description_chars', 4000));

        return [
            'schema_source' => ['required', Rule::enum(SchemaSource::class)],
            'dialect' => ['required', Rule::enum(SqlDialect::class)],
            'output_target' => [
                'required',
                'string',
                Rule::exists(OutputTarget::class, 'slug')->where('enabled', true),
            ],
            'schema' => ['required', 'string', 'max:'.$maxSchemaBytes],
            'description' => ['required', 'string', 'max:'.$maxDescriptionChars],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in([...Locales::codes(), ...Locales::slugs()])],
        ];
    }
}
