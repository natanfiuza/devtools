<?php

namespace App\Support\Colors\Moderation;

/**
 * Normaliza texto para comparar termos e comentarios (F7, ADR-140).
 *
 * A comparacao e por token, nunca por substring: pontuacao vira espaco,
 * maiusculas viram minusculas, zero-width e caracteres de controle somem. O
 * resultado nao preserva o texto original e nunca e registrado em log.
 */
final class CommentTextNormalizer
{
    public static function normalize(string $text): string
    {
        // Extensao intl e opcional no ambiente; sem ela seguimos com
        // mb_strtolower, sem NFKC.
        if (class_exists('Normalizer')) {
            $normalized = call_user_func(['Normalizer', 'normalize'], $text, 4);

            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        $text = mb_strtolower($text, 'UTF-8');

        // Zero-width e marcadores invisiveis usados para escapar do filtro.
        $text = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $text) ?? $text;

        // Controle e formato viram separador.
        $text = preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', $text) ?? $text;

        // Tudo que nao e letra/numero/espaco vira separador.
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return $text;
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $normalized = self::normalize($text);

        return $normalized === '' ? [] : explode(' ', $normalized);
    }
}
