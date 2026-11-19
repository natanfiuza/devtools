<?php

namespace App\Http\Requests\Colors;

use App\Models\LayoutSystem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Duplicacao de sistema de layout (F4, ADR-165).
 *
 * O corpo e vazio: a copia usa o retrato gravado na origem; nada aqui e
 * persistido na origem.
 */
final class DuplicateLayoutSystemRequest extends FormRequest
{
    /**
     * A duplicacao pelo dashboard so aceita sistema da propria conta (404
     * para outra conta). A rota publica nao tem `layoutSystem` na URL: o
     * controller resolve o sistema por `publiclyVisible`.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $system = $this->route('layoutSystem');

        if (! $user instanceof User) {
            return false;
        }

        if ($system === null) {
            return true;
        }

        return $system instanceof LayoutSystem
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
        return [];
    }
}
