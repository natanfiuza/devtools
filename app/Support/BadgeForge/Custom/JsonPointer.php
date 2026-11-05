<?php

namespace App\Support\BadgeForge\Custom;

/**
 * JSON Pointer RFC 6901 (F7, ADR-087).
 *
 * Aceita apenas escalar como metrica: objeto, lista, ausente ou `null`
 * viram "indisponivel", nunca uma badge com `[object Object]`. Em listas, o
 * token precisa ser indice canonico (sem zero a esquerda).
 */
final class JsonPointer
{
    public static function isValid(string $pointer): bool
    {
        $maxLength = max(1, (int) config('badgeforge.custom_providers.pointer_max_length', 256));

        if (mb_strlen($pointer, 'UTF-8') > $maxLength) {
            return false;
        }

        if ($pointer === '') {
            return true;
        }

        if (! str_starts_with($pointer, '/')) {
            return false;
        }

        foreach (explode('/', substr($pointer, 1)) as $token) {
            if (preg_match('/~(?![01])/', $token) === 1) {
                return false;
            }
        }

        return true;
    }

    public static function extract(mixed $document, string $pointer): string|int|float|bool|null
    {
        if (! self::isValid($pointer)) {
            return null;
        }

        $current = $document;

        if ($pointer === '') {
            return self::scalar($current);
        }

        foreach (explode('/', substr($pointer, 1)) as $rawToken) {
            $token = str_replace(['~1', '~0'], ['/', '~'], $rawToken);

            if (! is_array($current)) {
                return null;
            }

            if (array_is_list($current)) {
                if (preg_match('/\A(0|[1-9][0-9]*)\z/', $token) !== 1) {
                    return null;
                }

                $index = (int) $token;

                if (! array_key_exists($index, $current)) {
                    return null;
                }

                $current = $current[$index];

                continue;
            }

            if (! array_key_exists($token, $current)) {
                return null;
            }

            $current = $current[$token];
        }

        return self::scalar($current);
    }

    private static function scalar(mixed $value): string|int|float|bool|null
    {
        return is_string($value) || is_int($value) || is_float($value) || is_bool($value)
            ? $value
            : null;
    }
}
