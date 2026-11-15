<?php

namespace App\Support\Colors\Moderation;

/**
 * Filtro local sincrono de comentarios (F7, ADR-140).
 *
 * Retorna apenas sim/nao: o termo encontrado nunca e devolvido nem registrado.
 * A comparacao e por sequencia exata de tokens (ngrama), entao "class" nao casa
 * com "ass" e "assumption" nao casa com "sum". O filtro bloqueia antes de
 * gravar; a IA so ve o que passou por aqui.
 */
final class WordFilter
{
    public function __construct(
        private readonly WordLists $lists,
    ) {}

    public function matches(string $body, string $locale): bool
    {
        $tokens = CommentTextNormalizer::tokens($body);

        if ($tokens === []) {
            return false;
        }

        $phrases = $this->lists->phrasesForLocale($locale);

        if ($phrases === []) {
            return false;
        }

        $lengths = [];

        foreach ($phrases as $phrase) {
            $lengths[count($phrase)] = true;
        }

        $grams = [];

        foreach (array_keys($lengths) as $length) {
            $limit = count($tokens) - $length;

            for ($start = 0; $start <= $limit; $start++) {
                $grams[$length][implode(' ', array_slice($tokens, $start, $length))] = true;
            }
        }

        foreach ($phrases as $phrase) {
            if (isset($grams[count($phrase)][implode(' ', $phrase)])) {
                return true;
            }
        }

        return false;
    }
}
