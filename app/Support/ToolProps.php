<?php

namespace App\Support;

use App\Models\User;
use App\Support\Ai\AiToolProps;
use App\Support\Base64Image\Base64ImageToolProps;
use App\Support\ShareText\ShareTextToolProps;
use Illuminate\Http\Request;

/**
 * Props por tool na pagina generica (ADR-043).
 *
 * A pagina `tools/Show` nao conhece ferramenta nenhuma: recebe os quatro
 * limites de `config/tools.php` e o pacote que a tool da vez declarar. Tools
 * que rodam so no navegador recebem um objeto vazio - um contrato estavel, em
 * vez de uma prop que aparece e some.
 */
final class ToolProps
{
    public static function for(string $slug, ?User $user, ?Request $request = null): object
    {
        return match ($slug) {
            'ai-sql-builder' => (object) AiToolProps::for($user),
            'base64-image' => (object) Base64ImageToolProps::for($user),
            'share-text' => (object) ShareTextToolProps::for($user, $request),
            default => (object) [],
        };
    }
}
