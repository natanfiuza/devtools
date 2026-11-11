<?php

namespace App\Support;

use App\Models\ColorPalette;
use App\Support\Colors\PaletteOgStorage;
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
        // Telas de conta: preferencias, seguranca e tokens nao tem conteudo
        // proprio e nao podem ser indexadas (F7). Os nomes de rota do starter
        // kit nao usam o prefixo `settings.`, por isso entram um a um.
        'profile',
        'security',
        'appearance',
        'two-factor',
        'api-tokens',
        // Pagina de status de uma doacao: tem token na URL e dados de um
        // pagamento, nao conteudo para busca. `/donate` continua indexavel.
        'donate.status',
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
            'donate' => [
                'title' => (string) trans('donations.meta.title'),
                'description' => (string) trans('donations.meta.description'),
            ],
            'contact' => [
                'title' => (string) trans('contact.meta.title'),
                'description' => (string) trans('contact.meta.description'),
            ],
            'api-docs' => [
                'title' => (string) trans('api_docs.meta.title'),
                'description' => (string) trans('api_docs.meta.description'),
            ],
            'badgeforge-docs' => [
                'title' => (string) trans('docs.badgeforge.meta.title'),
                'description' => (string) trans('docs.badgeforge.meta.description'),
            ],
            'tools.show', 'tools.show.grouped' => self::toolMeta($request),
            'colors.palettes.show' => self::paletteMeta($request),
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
     * Metas da pagina publica de uma paleta.
     *
     * O titulo e o nome da paleta; a descricao e a do dono quando existe e,
     * senao, uma frase montada com a contagem de cores e as tags. A paleta e
     * lida pela mesma regra da rota (publica, ativa e com slug), entao nenhum
     * dado de paleta privada ou arquivada entra em meta.
     *
     * @return array{title: string, description: string}
     */
    private static function paletteMeta(Request $request): array
    {
        $palette = self::palette($request);

        if ($palette === null) {
            return [
                'title' => (string) config('app.name'),
                'description' => (string) trans('app.tagline'),
            ];
        }

        $description = trim((string) $palette->description);

        if ($description === '') {
            $tags = $palette->tags
                ->map(static fn ($tag): string => $tag->name)
                ->implode(', ');

            $key = $tags === '' ? 'colors.meta.description_no_tags' : 'colors.meta.description';

            $description = (string) trans($key, [
                'count' => (int) $palette->colors_count,
                'tags' => $tags,
            ]);
        }

        return [
            'title' => $palette->name,
            'description' => $description,
        ];
    }

    /**
     * A paleta da pagina publica, pela mesma visibilidade da rota.
     */
    private static function palette(Request $request): ?ColorPalette
    {
        $route = $request->route();

        if ($route?->getName() !== 'colors.palettes.show') {
            return null;
        }

        $slug = $route->parameter('slug');

        if (! is_string($slug)) {
            return null;
        }

        return ColorPalette::query()
            ->publiclyVisible()
            ->with('tags')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * A imagem OG gerada pelo dono, quando a paleta e publica e o arquivo esta
     * em disco. Sem ela, a pagina cai na imagem padrao do site.
     *
     * @return array{url: string, width: int, height: int, alt: string}|null
     */
    private static function paletteImage(Request $request): ?array
    {
        $palette = self::palette($request);

        if ($palette === null || ! $palette->hasOg()) {
            return null;
        }

        if (! (new PaletteOgStorage)->exists($palette->id)) {
            return null;
        }

        $locale = $request->route()?->parameter('locale');

        return [
            'url' => route('colors.palettes.og', [
                'locale' => is_string($locale) ? $locale : app()->getLocale(),
                'slug' => $palette->slug,
                'v' => $palette->og_version,
            ]),
            'width' => (int) config('colors.og.width', 1200),
            'height' => (int) config('colors.og.height', 630),
            'alt' => (string) trans('colors.meta.image_alt', ['name' => $palette->name]),
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
        $palette = self::paletteImage($request);

        if ($palette !== null) {
            return $palette;
        }

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
    public static function toolSlug(Request $request): ?string
    {
        $route = $request->route();
        $name = $route?->getName();

        // So as rotas de tool tem imagem propria: nas outras nem se abre o
        // registro.
        if (! is_string($name) || ! in_array($name, ['tools.show', 'tools.show.grouped'], true)) {
            return null;
        }

        $slug = $route->parameter('slug');
        $group = $route->parameter('group');

        if (! is_string($slug)) {
            return null;
        }

        $tool = Tools::findForUrl($slug, is_string($group) ? $group : null);

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
