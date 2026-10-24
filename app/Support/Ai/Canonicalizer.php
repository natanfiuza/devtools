<?php

namespace App\Support\Ai;

use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Support\Locales;
use InvalidArgumentException;

/**
 * Normaliza a entrada antes de ela virar `CanonicalPayload`.
 *
 * A normalizacao existe para o cache: CRLF x LF e espacos no fim da linha nao
 * mudam o schema, entao nao podem gerar dois hashes. Espacos internos e
 * literais do schema ficam intocados - mexer neles mudaria o dado que o modelo
 * recebe.
 */
final class Canonicalizer
{
    public function canonicalize(
        SchemaSource $schemaSource,
        SqlDialect $dialect,
        string $target,
        string $schema,
        string $description,
        string $model,
        int $promptVersion,
        string $locale = 'en',
    ): CanonicalPayload {
        $schema = $this->normalize($schema);
        $description = $this->normalize($description);
        $target = trim($target);
        $model = trim($model);
        $locale = trim($locale);

        $hasConfig = function_exists('app') && app()->has('config');

        if ($hasConfig) {
            if (! Locales::isSupported($locale)) {
                $locale = Locales::fromSlug($locale) ?? Locales::fallback();
            }
        } else {
            $slugs = ['en' => 'en', 'pt-br' => 'pt_BR', 'pt-pt' => 'pt_PT', 'fr' => 'fr', 'es' => 'es'];
            $supported = ['en', 'pt_BR', 'pt_PT', 'fr', 'es'];

            if (! in_array($locale, $supported, true)) {
                $locale = $slugs[strtolower($locale)] ?? 'en';
            }
        }

        if ($schema === '') {
            throw new InvalidArgumentException('The schema must not be empty.');
        }

        if ($description === '') {
            throw new InvalidArgumentException('The description must not be empty.');
        }

        if (preg_match('/^[a-z0-9_]{1,32}$/', $target) !== 1) {
            throw new InvalidArgumentException('The target slug is invalid.');
        }

        if ($model === '') {
            throw new InvalidArgumentException('The model must not be empty.');
        }

        if ($promptVersion < 1) {
            throw new InvalidArgumentException('The prompt version must be positive.');
        }

        return new CanonicalPayload(
            $schemaSource,
            $dialect,
            $target,
            $schema,
            $description,
            $model,
            $promptVersion,
            $locale,
        );
    }

    private function normalize(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = preg_replace('/[ \t]+$/m', '', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
