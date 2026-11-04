<?php

namespace App\Support\BadgeForge\Providers;

use App\Models\BadgeProviderDefinition;

/**
 * Converte o `{path}` da URL publica em params tipados pela definicao
 * (F4, ADR-080).
 *
 * Rejeita vazio, control chars, segmentos `.`/`..`, path fora do pattern,
 * tamanho acima de `providers.max_param_length` e contagem errada de
 * segmentos. Um unico param pode aceitar `/` (pacote npm com escopo).
 *
 * Definicao sem params nao tem coordenadas: `path_pattern` e o literal da
 * URL publica (ex.: `latest`) e o parse so devolve `[]` quando o path casa
 * exatamente com ele (F6, ADR-085).
 */
final class ProviderPathParser
{
    /**
     * @return array<string, string>|null
     */
    public function parse(BadgeProviderDefinition $definition, string $path): ?array
    {
        $params = ProviderParam::listFrom($definition->required_params);

        if ($params === null || $path === '') {
            return null;
        }

        if (str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return null;
        }

        if (str_starts_with($path, '/') || str_ends_with($path, '/')) {
            return null;
        }

        $maxLength = max(1, (int) config('badgeforge.providers.max_param_length', 200));

        if ($params === []) {
            return $this->parseLiteral($definition, $path, $maxLength);
        }

        if (strlen($path) > $maxLength * max(1, count($params))) {
            return null;
        }

        if (count($params) === 1 && $params[0]->allowSlash) {
            $values = [$params[0]->name => $path];
        } else {
            $segments = explode('/', $path);

            if (count($segments) !== count($params)) {
                return null;
            }

            $values = [];

            foreach ($params as $index => $param) {
                $values[$param->name] = $segments[$index];
            }
        }

        foreach ($params as $param) {
            $value = $values[$param->name];

            if ($value === '' || strlen($value) > $maxLength || $this->hasDotSegment($value)) {
                return null;
            }

            if ($this->matches($param->pattern, $value) !== true) {
                return null;
            }
        }

        return $values;
    }

    /**
     * @return array<string, string>|null
     */
    private function parseLiteral(
        BadgeProviderDefinition $definition,
        string $path,
        int $maxLength,
    ): ?array {
        $literal = trim((string) $definition->path_pattern);

        if (preg_match('/\A[A-Za-z0-9._-]+\z/', $literal) !== 1) {
            return null;
        }

        if (strlen($literal) > $maxLength || $path !== $literal || $this->hasDotSegment($literal)) {
            return null;
        }

        return [];
    }

    private function hasDotSegment(string $value): bool
    {
        foreach (explode('/', $value) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    private function matches(string $pattern, string $value): bool
    {
        $matched = @preg_match('~\A(?:'.$pattern.')\z~u', $value);

        return $matched === 1;
    }
}
