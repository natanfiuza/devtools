<?php

namespace App\Support;

use App\Models\User;
use App\Support\Ai\AiToolProps;

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
    public static function for(string $slug, ?User $user): object
    {
        return match ($slug) {
            'ai-sql-builder' => (object) AiToolProps::for($user),
            default => (object) [],
        };
    }
}
