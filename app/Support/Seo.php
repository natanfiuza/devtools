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
        'subscribe',
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
     * Titulo e descricao da pagina, resolvidos no servidor.
     *
     * E a fonte unica das metas: o app.blade.php escreve `<title>`,
     * `meta description`, `og:title` e `og:description` a partir daqui, e o
     * `<Head>` do Inertia cuida so do titulo na navegacao do cliente. Assim as
     * metas nao dependem de o SSR estar no ar - robo de preview nao executa JS.
     *
     * Telas que nao entram no indice nao tem meta de pagina: voltam vazias.
     *
     * @return array{title: string, description: string}
     */
    public static function pageMeta(Request $request): array
    {
        if (self::noindex($request)) {
            return ['title' => '', 'description' => ''];
        }

        return match ($request->route()?->getName()) {
            'home' => [
                'title' => (string) trans('app.nav.tools'),
                'description' => (string) trans('home.subtitle'),
            ],
            'pricing' => [
                'title' => (string) trans('home.pricing.title'),
                'description' => (string) trans('home.pricing.subtitle'),
            ],
            'privacy' => [
                'title' => (string) trans('legal.privacy.title'),
                'description' => self::legalIntro('legal.privacy.intro'),
            ],
            'terms' => [
                'title' => (string) trans('legal.terms.title'),
                'description' => self::legalIntro('legal.terms.intro'),
            ],
            'tools.show' => self::toolMeta($request),
            default => [
                'title' => (string) config('app.name'),
                'description' => (string) trans('app.tagline'),
            ],
        };
    }

    /**
     * O titulo da aba, no mesmo formato do appTitle() do frontend.
     *
     * Pagina sem titulo (noindex) ou com o proprio nome do app fica so com o
     * nome do app: evita "devtools - devtools".
     */
    public static function documentTitle(string $title): string
    {
        $app = (string) config('app.name');

        return $title === '' || $title === $app ? $app : $title.' - '.$app;
    }

    /**
     * Metas de compartilhamento (Open Graph) das paginas indexaveis.
     *
     * O titulo e a descricao de cada pagina ficam em pageMeta() e sao escritos
     * pelo app.blade.php junto com a view. Aqui ficam so os dados do site
     * inteiro: tipo, nome, idioma (com os outros na lista de alternates) - e a
     * imagem, que e da pagina da tool quando ela tem uma.
     *
     * @return array{
     *     type: string,
     *     siteName: string,
     *     locale: string,
     *     alternates: list<string>,
     *     image: array{url: string, width: int, height: int, alt: string}
     * }
     */
    public static function openGraph(Request $request): array
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
            'image' => self::image($request),
        ];
    }

    /**
     * Metas da pagina de uma tool que funciona.
     *
     * O nome e a descricao vem do i18n pelo slug; o slug ja foi conferido no
     * registro por toolSlug() - a URL crua nunca decide nada. Sem slug valido,
     * a pagina cai no fallback do site.
     *
     * @return array{title: string, description: string}
     */
    private static function toolMeta(Request $request): array
    {
        $slug = self::toolSlug($request);

        if ($slug === null) {
            return [
                'title' => (string) config('app.name'),
                'description' => (string) trans('app.tagline'),
            ];
        }

        return [
            'title' => (string) trans("tools.{$slug}.name"),
            'description' => (string) trans("tools.{$slug}.description"),
        ];
    }

    /**
     * A introducao das paginas legais interpolada como o vue-i18n faz.
     *
     * O Laravel troca `:chave`; o texto de `legal.*.intro` usa `{app}`, que no
     * frontend e interpolado pelo vue-i18n. O servidor precisa fazer o mesmo
     * para a meta nao sair com o marcador cru.
     */
    private static function legalIntro(string $key): string
    {
        return str_replace('{app}', (string) trans('app.brand'), (string) trans($key));
    }

    /**
     * A imagem do card: a da propria tool, quando ela tem uma, senao a padrao.
     *
     * A largura e a altura sao as mesmas nos dois casos - as duas imagens saem
     * do mesmo desenho (app/Console/Commands/GenerateOgImage.php) -, entao as
     * metas `og:image:width/height` nao mentem.
     *
     * @return array{url: string, width: int, height: int, alt: string}
     */
    private static function image(Request $request): array
    {
        $url = self::imageUrl((string) config('seo.image.file'));
        // A descricao diz o que a imagem mostra: a marca e a frase do site.
        $alt = trans('app.brand').' - '.trans('app.tagline');
        $slug = self::toolSlug($request);

        if ($slug !== null) {
            $relative = trim((string) config('seo.image.tools_dir'), '/').'/'.$slug.'.png';

            // Sem o PNG em disco, a pagina cai na imagem padrao: um `og:image`
            // apontando para 404 estraga o card pior do que uma imagem generica.
            if (is_file(public_path($relative))) {
                $url = self::imageUrl($relative);
                // O nome da tool no idioma da pagina - a marca ja esta na
                // imagem, entao a descricao comeca por ela.
                $alt = trans("tools.{$slug}.name").' - '.trans('app.tagline');
            }
        }

        return [
            'url' => $url,
            'width' => (int) config('seo.image.width'),
            'height' => (int) config('seo.image.height'),
            'alt' => $alt,
        ];
    }

    /**
     * URL publica da imagem, com a versao de cache na query string.
     *
     * A query so existe na URL: o arquivo em disco continua sendo procurado
     * em public_path() sem ela (ver image()).
     */
    private static function imageUrl(string $relative): string
    {
        return url($relative).'?v='.(int) config('seo.image.version');
    }

    /**
     * O slug da tool quando a pagina e a de uma ferramenta que funciona.
     *
     * O slug e conferido no **registro** - nunca usado cru da URL para montar
     * caminho - e so tool `available` tem imagem: e a mesma regra que decide se
     * a pagina existe (App\Http\Controllers\ToolController).
     */
    private static function toolSlug(Request $request): ?string
    {
        $route = $request->route();
        $name = $route?->getName();

        // Só a rota da tool tem imagem propria: nas outras nem se abre o
        // registro.
        if (! is_string($name) || $name !== 'tools.show') {
            return null;
        }

        $slug = $route->parameter('slug');

        if (! is_string($slug)) {
            return null;
        }

        $tool = Tools::find($slug);

        return $tool === null || $tool['status'] !== 'available' ? null : $slug;
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
