<?php

namespace App\Support\BadgeForge;

/**
 * Traduz path + query em BadgeSpec.
 *
 * Regras: `_` vira espaco, `__` vira `_`, `-` e literal; caractere de
 * controle vira espaco; texto acima do limite trunca com reticencias;
 * qualquer valor cosmetico invalido cai no default. Nunca lanca excecao
 * por entrada do usuario.
 */
final class BadgeRequest
{
    public function __construct(private readonly BadgeSpecBuilder $builder) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function spec(string $labelSegment, string $messageSegment, array $query): ?BadgeSpec
    {
        $label = self::decodeText($labelSegment);
        $message = self::decodeText($messageSegment);

        if ($label === null || $message === null) {
            return null;
        }

        return $this->builder->build($label, $message, $query);
    }

    public static function decodeText(string $segment): ?string
    {
        if (! mb_check_encoding($segment, 'UTF-8')) {
            return null;
        }

        $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $segment);

        if (! is_string($cleaned)) {
            return null;
        }

        // O router decodifica o path antes do match, entao `%2F` literal ja
        // teria virado separador. A barra viaja dupla-codificada (`%252F`):
        // chega aqui como `%2F` e vira `/`. A colisao com um `%2F` digitado
        // pelo usuario e documentada na API.
        $cleaned = str_ireplace('%2F', '/', $cleaned);

        $sentinel = "\x1A";
        $text = str_replace('__', $sentinel, $cleaned);
        $text = str_replace('_', ' ', $text);
        $text = str_replace($sentinel, '_', $text);

        return trim($text) === '' ? null : $text;
    }

    public static function truncate(string $text, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }

        $characters = mb_str_split($text, 1, 'UTF-8');

        if (count($characters) <= $limit) {
            return $text;
        }

        return implode('', array_slice($characters, 0, $limit - 1)).'…';
    }
}
