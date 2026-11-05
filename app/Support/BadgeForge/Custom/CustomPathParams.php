<?php

namespace App\Support\BadgeForge\Custom;

/**
 * Coordenadas de path de um provider customizado (F7, ADR-087).
 *
 * O numero de segmentos precisa ser exatamente o numero de `path_params`.
 * Cada segmento aceita apenas caracteres seguros de path; vazio, `.`, `..` e
 * barra codificada sao recusados antes de qualquer fetch.
 */
final class CustomPathParams
{
    /**
     * @param  list<string>  $names
     * @return array<string, string>|null
     */
    public static function parse(string $path, array $names): ?array
    {
        if ($names === [] || $path === '' || str_starts_with($path, '/')) {
            return null;
        }

        $segments = explode('/', $path);

        if (count($segments) !== count($names)) {
            return null;
        }

        $maxLength = max(1, (int) config('badgeforge.custom_providers.param_max_length', 200));
        $values = [];

        foreach ($segments as $index => $segment) {
            if ($segment === '.' || $segment === '..' || $segment === '') {
                return null;
            }

            if (mb_strlen($segment, 'UTF-8') > $maxLength) {
                return null;
            }

            if (preg_match('/\A[A-Za-z0-9._~-]+\z/', $segment) !== 1) {
                return null;
            }

            $values[$names[$index]] = $segment;
        }

        return $values;
    }
}
