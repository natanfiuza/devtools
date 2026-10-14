<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tema claro/escuro/sistema do visitante.
 *
 * O valor e resolvido no servidor para que o <html> ja chegue com a classe
 * certa (sem flash). A gravacao da coluna users.appearance fica para a fase
 * de configuracoes da conta; aqui ela so e lida, quando existir.
 */
final class Appearance
{
    public const LIGHT = 'light';

    public const DARK = 'dark';

    public const SYSTEM = 'system';

    /**
     * Valores aceitos.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::LIGHT, self::DARK, self::SYSTEM];
    }

    public static function isSupported(mixed $value): bool
    {
        return is_string($value) && in_array($value, self::values(), strict: true);
    }

    /**
     * Preferencia do visitante, na ordem: cookie, coluna do usuario, sistema.
     */
    public static function resolve(Request $request): string
    {
        // O cookie vem do cliente: pode nao existir, vir repetido (array) ou
        // com qualquer valor. So um dos tres aceitos passa daqui.
        $cookie = $request->cookie('appearance');

        if (is_string($cookie) && self::isSupported($cookie)) {
            return $cookie;
        }

        $appearance = $request->user()?->appearance;

        if (is_string($appearance) && self::isSupported($appearance)) {
            return $appearance;
        }

        return self::SYSTEM;
    }
}
