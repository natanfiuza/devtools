<?php

namespace App\Support\Colors\Moderation;

/**
 * Listas locais de bloqueio por idioma (F7, ADR-140).
 *
 * Cada locale tem seu arquivo em `resources/colors/moderation`; a lista
 * inglesa e sempre mesclada como fallback. Diretorio ausente, arquivo ausente
 * ou linha invalida nao derruba a requisicao: o filtro simplesmente nao tem o
 * que comparar e a moderacao por IA continua. O cache fica em memoria por
 * caminho+locale, sem tocar no banco nem no cache da aplicacao.
 */
final class WordLists
{
    /**
     * @var array<string, list<list<string>>>
     */
    private static array $cache = [];

    /**
     * @return list<list<string>>
     */
    public function phrasesForLocale(string $locale): array
    {
        $directory = config('colors.comments.moderation.word_lists_path');
        $directory = is_string($directory) && trim($directory) !== ''
            ? rtrim(trim($directory), '/\\')
            : '';

        $locale = self::safeLocale($locale);
        $key = $directory.'|'.$locale;

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $phrases = [];

        foreach (array_unique(['en', $locale]) as $file) {
            if ($directory === '') {
                break;
            }

            foreach ($this->parse($directory.DIRECTORY_SEPARATOR.$file.'.txt') as $phrase) {
                $phrases[implode(' ', $phrase)] = $phrase;
            }
        }

        return self::$cache[$key] = array_values($phrases);
    }

    /**
     * @return list<list<string>>
     */
    private function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $phrases = [];

        foreach (preg_split('/\R/u', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $tokens = CommentTextNormalizer::tokens($line);

            if ($tokens !== []) {
                $phrases[] = $tokens;
            }
        }

        return $phrases;
    }

    private static function safeLocale(string $locale): string
    {
        $safe = strtolower((string) preg_replace('/[^a-zA-Z_]/', '', $locale));

        return $safe === '' ? 'en' : $safe;
    }
}
