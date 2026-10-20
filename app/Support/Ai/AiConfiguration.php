<?php

namespace App\Support\Ai;

use App\Exceptions\Ai\AiUnavailableException;

/**
 * Leitura da configuracao do provedor, sem valores padrao no codigo.
 *
 * `AI_BASE_URL`, `AI_MODEL` e `AI_API_KEY` sao as tres chaves que ligam a tool.
 * Quando qualquer uma falta, a pagina continua respondendo, mas a API recusa
 * com `503` - melhor do que enfileirar um job que so pode falhar.
 */
final class AiConfiguration
{
    public static function ensureAvailable(): void
    {
        self::baseUrl();
        self::model();
        self::apiKey();
    }

    public static function isAvailable(): bool
    {
        return self::value('ai.base_url') !== null
            && self::value('ai.model') !== null
            && self::value('ai.api_key') !== null;
    }

    public static function baseUrl(): string
    {
        return self::required('ai.base_url');
    }

    public static function model(): string
    {
        return self::required('ai.model');
    }

    public static function apiKey(): string
    {
        return self::required('ai.api_key');
    }

    private static function required(string $key): string
    {
        $value = self::value($key);

        if ($value === null) {
            throw AiUnavailableException::notConfigured();
        }

        return $value;
    }

    private static function value(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
