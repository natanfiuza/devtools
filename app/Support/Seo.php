<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Regras de indexacao do site.
 *
 * Telas de acesso e paginas de token (confirmacao/descadastro) ficam fora do
 * indice: nao tem conteudo proprio, nao devem aparecer na busca e nao ganham
 * hreflang. As paginas publicas de conteudo continuam indexaveis.
 */
final class Seo
{
    /**
     * Rotas (nome exato ou prefixo) que nao entram no indice.
     *
     * @var list<string>
     */
    private const NOINDEX = [
        'login',
        'logout',
        'register',
        'password.',
        'verification.',
        'newsletter.confirm',
        'newsletter.unsubscribe',
    ];

    public static function noindex(Request $request): bool
    {
        $name = $request->route()?->getName();

        if (! is_string($name) || $name === '') {
            return false;
        }

        foreach (self::NOINDEX as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
