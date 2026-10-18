<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Wordmark `devtools_` dos e-mails transacionais (DESIGN.md).
 *
 * O template de e-mail usa fundo claro (#fafafa / #ffffff), entao o cursor `_`
 * sai no token `--brand` do tema claro (#0f7f8a, contraste AA validado).
 */
final class MailBrand
{
    public const BRAND_COLOR = '#0f7f8a';

    /**
     * Wordmark em HTML (`devtools_` com `_` em `--brand`).
     */
    public static function wordmark(): HtmlString
    {
        return new HtmlString(
            e((string) config('app.name', 'devtools'))
            .'<span style="color: '.self::BRAND_COLOR.';">_</span>'
        );
    }

    /**
     * Wordmark em texto puro (`devtools_`).
     */
    public static function plainWordmark(): string
    {
        return config('app.name', 'devtools').'_';
    }

    /**
     * Traduz uma linha de e-mail escapando o texto e interpolando `:app` com o
     * wordmark `devtools_` colorido.
     *
     * @param  array<string, string|int>  $replace
     */
    public static function line(string $key, array $replace = []): HtmlString
    {
        $placeholder = '__DEVTOOLS_WORDMARK_PLACEHOLDER__';

        $translated = __($key, array_merge($replace, ['app' => $placeholder]));
        $escaped = e($translated);

        return new HtmlString(
            str_replace($placeholder, self::wordmark()->toHtml(), $escaped)
        );
    }
}
