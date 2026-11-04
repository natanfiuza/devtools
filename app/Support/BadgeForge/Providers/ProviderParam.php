<?php

namespace App\Support\BadgeForge\Providers;

/**
 * Parametro de path de uma definicao de provider, normalizado a partir do
 * JSON `required_params` (F4, ADR-080).
 *
 * Um JSON invalido nunca vira excecao em tempo de request: `listFrom()`
 * devolve `null` e a definicao e tratada como indisponivel (404).
 */
final readonly class ProviderParam
{
    public function __construct(
        public string $name,
        public string $pattern,
        public bool $allowSlash,
        public string $placeholderKey,
    ) {}

    /**
     * @return list<self>|null
     */
    public static function listFrom(mixed $raw): ?array
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $params = [];
        $names = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                return null;
            }

            $name = $item['name'] ?? null;
            $pattern = $item['pattern'] ?? null;
            $allowSlash = $item['allowSlash'] ?? false;
            $placeholderKey = $item['placeholderKey'] ?? $name;

            if (! is_string($name) || preg_match('/\A[a-z][a-z0-9_]*\z/', $name) !== 1) {
                return null;
            }

            if (isset($names[$name])) {
                return null;
            }

            if (! is_string($pattern) || trim($pattern) === '' || str_contains($pattern, '~')) {
                return null;
            }

            if (! is_bool($allowSlash) || ! is_string($placeholderKey) || $placeholderKey === '') {
                return null;
            }

            $names[$name] = true;
            $params[] = new self($name, $pattern, $allowSlash, $placeholderKey);
        }

        if (count(array_filter($params, static fn (self $param): bool => $param->allowSlash)) > 1) {
            return null;
        }

        return $params;
    }
}
