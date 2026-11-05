<?php

namespace App\Support\BadgeForge\Custom;

/**
 * Template de URL de um provider customizado (F7, ADR-087).
 *
 * Os placeholders `{nome}` so podem aparecer no path/query: esquema e host
 * ficam fixos, para que o dono nao troque de origem por parametro. O conjunto
 * e a ordem precisam bater exatamente com `path_params`. A renderizacao
 * codifica cada valor, nunca a URL inteira.
 */
final readonly class CustomProviderUrlTemplate
{
    /**
     * @param  list<string>  $placeholders
     */
    private function __construct(
        public string $template,
        public array $placeholders,
    ) {}

    public static function from(string $template): ?self
    {
        $template = trim($template);
        $maxLength = max(1, (int) config('badgeforge.custom_providers.url_max_length', 2048));

        if ($template === '' || mb_strlen($template, 'UTF-8') > $maxLength) {
            return null;
        }

        if (preg_match('/[\x00-\x20\x7F\\\\#]/', $template) === 1) {
            return null;
        }

        if (preg_match('/\Ahttps:\/\//i', $template) !== 1) {
            return null;
        }

        if (preg_match_all('/\{([^{}]*)\}/', $template, $matches) === false) {
            return null;
        }

        $placeholders = $matches[1];

        if ($placeholders === []) {
            return null;
        }

        $stripped = preg_replace('/\{[^{}]*\}/', '', $template);

        if (! is_string($stripped) || str_contains($stripped, '{') || str_contains($stripped, '}')) {
            return null;
        }

        if (! self::placeholdersOutsideAuthority($template)) {
            return null;
        }

        $seen = [];

        foreach ($placeholders as $name) {
            if (preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,31}\z/', $name) !== 1) {
                return null;
            }

            if (isset($seen[$name])) {
                return null;
            }

            $seen[$name] = true;
        }

        if (count($placeholders) > max(1, (int) config('badgeforge.custom_providers.max_path_params', 5))) {
            return null;
        }

        return new self($template, $placeholders);
    }

    /**
     * @param  list<string>  $pathParams
     */
    public function matchesPathParams(array $pathParams): bool
    {
        return $this->placeholders === $pathParams;
    }

    /**
     * @param  array<string, string>  $values
     */
    public function render(array $values): ?string
    {
        $replacements = [];

        foreach ($this->placeholders as $name) {
            $value = $values[$name] ?? null;
            $maxLength = max(1, (int) config('badgeforge.custom_providers.param_max_length', 200));

            if (! is_string($value) || $value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
                return null;
            }

            $replacements['{'.$name.'}'] = rawurlencode($value);
        }

        return strtr($this->template, $replacements);
    }

    /**
     * Nenhum placeholder antes da primeira `/` depois do esquema: host e
     * autoridade sao literais.
     */
    private static function placeholdersOutsideAuthority(string $template): bool
    {
        $offset = stripos($template, '://');

        if ($offset === false) {
            return false;
        }

        $authorityStart = $offset + 3;
        $end = strcspn($template, '/?', $authorityStart);
        $authority = substr($template, $authorityStart, $end);

        return str_contains($authority, '{') === false && str_contains($authority, '}') === false;
    }
}
