<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Regras de indexacao e de compartilhamento do site.
 *
 * Telas de acesso e paginas de token (confirmacao/descadastro) ficam fora do
 * indice: nao tem conteudo proprio, nao devem aparecer na busca e nao ganham
 * hreflang. As paginas publicas de conteudo continuam indexaveis - e sao elas
 * que ganham as metas de compartilhamento (openGraph).
 */
final class Seo
{
    /**
     * Rotas (nome exato ou prefixo) que nao entram no indice.
     *
     * @var list<string>
     */
    private const NOINDEX = [
        'login',
        'logout',
        'register',
        'password.',
        'verification.',
        'newsletter.confirm',
        'newsletter.unsubscribe',
        // Guia de estilo: pagina de referencia do DESIGN, nao e conteudo do
        // site - fica fora do indice e do sitemap (mas continua navegavel).
        'styleguide',
        // Area autenticada: nao tem conteudo proprio nem versao por idioma, e
        // o painel administrativo nao deve aparecer em busca nenhuma.
        'dashboard',
        'settings',
        'admin',
    ];

    public static function noindex(Request $request): bool
    {
        $name = $request->route()?->getName();

        if (! is_string($name) || $name === '') {
            return false;
        }

        foreach (self::NOINDEX as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Metas de compartilhamento (Open Graph) das paginas indexaveis.
     *
     * O titulo e a descricao de cada pagina nao estao aqui: os dois saem do
     * `<Head>` do Inertia, junto com o `<title>` - com SSR, os tres aparecem no
     * HTML do servidor. O que sobra para a view e o que vale para o site
     * inteiro: tipo, nome, idioma (com os outros na lista de alternates) e a
     * imagem padrao.
     *
     * @return array{
     *     type: string,
     *     siteName: string,
     *     locale: string,
     *     alternates: list<string>,
     *     image: array{url: string, width: int, height: int, alt: string}
     * }
     */
    public static function openGraph(): array
    {
        $locale = self::ogLocale(app()->getLocale());

        $alternates = [];

        foreach (Locales::codes() as $code) {
            $other = self::ogLocale($code);

            if ($other !== $locale) {
                $alternates[] = $other;
            }
        }

        return [
            'type' => 'website',
            'siteName' => (string) config('app.name'),
            'locale' => $locale,
            'alternates' => $alternates,
            'image' => [
                'url' => url((string) config('seo.image.file')),
                'width' => (int) config('seo.image.width'),
                'height' => (int) config('seo.image.height'),
                // Descreve o que a imagem mostra: a marca e a frase do site.
                'alt' => trans('app.brand').' - '.trans('app.tagline'),
            ],
        ];
    }

    /**
     * Codigo do idioma no formato do `og:locale` (idioma_TERRITORIO): quem tem
     * territorio troca o hifen pelo sublinhado (pt-BR -> pt_BR).
     */
    private static function ogLocale(string $code): string
    {
        return str_replace('-', '_', Locales::html($code));
    }
}
