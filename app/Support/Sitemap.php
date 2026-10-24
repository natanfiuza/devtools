<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Sitemap do site (docs/SPEC.md secao 7).
 *
 * Lista so o que entra no indice: as paginas publicas de conteudo, nos cinco
 * idiomas, cada uma com as irmas dela (o `hreflang` do sitemap e o mesmo que as
 * paginas declaram) - e a tool so quando ela ja funciona.
 *
 * As telas de acesso ficam de fora por construcao: a lista abaixo e de paginas
 * de conteudo, e o teste confere, uma a uma, que nenhuma delas pede `noindex`.
 */
final class Sitemap
{
    /**
     * Paginas de conteudo, na ordem em que aparecem no arquivo.
     *
     * A rota da tool nao esta aqui: cada ferramenta entra pelo slug, e so
     * quando ja funciona (docs/SPEC.md secao 9).
     *
     * @var list<string>
     */
    private const PAGES = [
        'home',
        'pricing',
        'privacy',
        'terms',
        'donate',
        'contact',
    ];

    /**
     * As URLs do sitemap, uma entrada por idioma.
     *
     * @return list<array{loc: string, alternates: array<string, string>, xdefault: string}>
     */
    public static function urls(): array
    {
        $urls = [];

        foreach (self::PAGES as $name) {
            $urls = [...$urls, ...self::entries($name)];
        }

        // Sem a rota da tool nao ha URL para listar: uma tool que nao funciona
        // nao entra no indice de qualquer forma.
        if (! Route::has('tools.show')) {
            return $urls;
        }

        foreach (Tools::available() as $tool) {
            $urls = [...$urls, ...self::entries('tools.show', ['slug' => $tool['slug']])];
        }

        return $urls;
    }

    /**
     * As URLs de uma pagina nos cinco idiomas.
     *
     * Cada entrada carrega as irmas, inclusive ela mesma - e o que a
     * especificacao do `xhtml:link` pede - e o `x-default`, que aponta para o
     * idioma de fallback, igual ao hreflang das paginas.
     *
     * @param  array<string, string>  $params  Parametros da rota, alem do idioma.
     * @return list<array{loc: string, alternates: array<string, string>, xdefault: string}>
     */
    private static function entries(string $name, array $params = []): array
    {
        $alternates = [];

        foreach (Locales::all() as $locale) {
            $alternates[$locale['html']] = route($name, ['locale' => $locale['slug'], ...$params]);
        }

        $xdefault = $alternates[Locales::html(Locales::fallback())] ?? '';

        $urls = [];

        foreach ($alternates as $loc) {
            $urls[] = [
                'loc' => $loc,
                'alternates' => $alternates,
                'xdefault' => $xdefault,
            ];
        }

        return $urls;
    }
}
