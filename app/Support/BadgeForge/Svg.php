<?php

namespace App\Support\BadgeForge;

use RuntimeException;

/**
 * Escaping e validacao do documento SVG.
 *
 * O renderer e um template fechado: nenhum markup do usuario entra no
 * documento. Mesmo assim, `assertSafe` rele o resultado e recusa tag ou
 * atributo fora da allowlist - uma segunda barreira, sem custo perceptivel.
 */
final class Svg
{
    private const TAGS = [
        'svg', 'title', 'a', 'defs', 'lineargradient', 'stop',
        'clippath', 'g', 'rect', 'path', 'text',
    ];

    public static function escape(string $value): string
    {
        return str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&#039;'],
            $value,
        );
    }

    /**
     * Formata numero decimal sem zeros a direita ("0.6", "0.125", "1").
     */
    public static function number(float $value, int $precision = 3): string
    {
        $formatted = number_format($value, $precision, '.', '');
        $formatted = rtrim($formatted, '0');

        return rtrim($formatted, '.');
    }

    public static function assertSafe(string $svg): void
    {
        if (preg_match('/<\s*(script|style|foreignObject|iframe|image|use|animate|set)\b/i', $svg) === 1) {
            throw new RuntimeException('SVG com tag proibida.');
        }

        preg_match_all('/<[^>]*>/', $svg, $elements);

        foreach ($elements[0] as $element) {
            // Valores sao delimitados por aspas reais; texto do usuario chega
            // escapado (&quot;), entao remover os valores elimina falsos
            // positivos sem esconder um atributo real.
            $structure = preg_replace('/="[^"]*"/', '=""', $element) ?? $element;

            if (preg_match('/\son[a-z]+\s*=/i', $structure) === 1) {
                throw new RuntimeException('SVG com atributo de evento.');
            }

            if (stripos($structure, 'javascript:') !== false) {
                throw new RuntimeException('SVG com URL javascript.');
            }
        }

        preg_match_all('/<\s*\/?\s*([a-zA-Z][a-zA-Z0-9:-]*)/', $svg, $matches);

        foreach ($matches[1] as $tag) {
            if (! in_array(strtolower($tag), self::TAGS, true)) {
                throw new RuntimeException("SVG com tag fora da allowlist: {$tag}");
            }
        }
    }
}
