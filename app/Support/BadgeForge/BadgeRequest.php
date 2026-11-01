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
    public function __construct(
        private readonly StyleCatalog $styles,
        private readonly LogoCatalog $logos,
    ) {}

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

        $style = $this->styles->get(self::queryString($query, 'style'));

        if ($style->uppercase) {
            $label = mb_strtoupper($label, 'UTF-8');
            $message = mb_strtoupper($message, 'UTF-8');
        }

        $maxLength = (int) config('badgeforge.limits.max_text_length', 100);
        $label = self::truncate($label, $maxLength);
        $message = self::truncate($message, $maxLength);

        $labelColor = $style->labelColor
            ?? Color::parseOr(self::queryString($query, 'labelColor'), (string) config('badgeforge.colors.label'));

        $messageColor = $style->messageColor
            ?? Color::parseOr(self::queryString($query, 'color'), (string) config('badgeforge.colors.default'));

        $logo = $this->logos->find(self::queryString($query, 'logo'));

        $logoSize = self::integer(
            self::queryString($query, 'logoSize'),
            $style->logoSize,
            (int) config('badgeforge.limits.logo_size.min', 8),
            (int) config('badgeforge.limits.logo_size.max', 20),
        );

        $logoPosition = self::queryString($query, 'logoPosition') === 'right' ? 'right' : 'left';

        $cacheSeconds = self::integer(
            self::queryString($query, 'cacheSeconds'),
            (int) config('badgeforge.limits.cache_seconds.default', 86400),
            (int) config('badgeforge.limits.cache_seconds.min', 300),
            (int) config('badgeforge.limits.cache_seconds.max', 86400),
        );

        return new BadgeSpec(
            label: $label,
            message: $message,
            style: $style,
            labelColor: $labelColor,
            messageColor: $messageColor,
            logo: $logo,
            logoColor: Color::parseOr(self::queryString($query, 'logoColor'), (string) config('badgeforge.colors.logo')),
            logoSize: $logoSize,
            logoPosition: $logoPosition,
            link: self::link(self::queryString($query, 'link')),
            cacheSeconds: $cacheSeconds,
        );
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

    /**
     * @param  array<string, mixed>  $query
     */
    private static function queryString(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private static function integer(?string $raw, int $default, int $min, int $max): int
    {
        $value = $raw !== null && preg_match('/^\d{1,9}$/', trim($raw)) === 1
            ? (int) trim($raw)
            : $default;

        return max($min, min($max, $value));
    }

    private static function link(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);
        $maxLength = (int) config('badgeforge.limits.link_max_length', 2048);

        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            return null;
        }

        if (preg_match('~^https?://[^\s"\'<>]+$~i', $value) !== 1) {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $value : null;
    }
}
