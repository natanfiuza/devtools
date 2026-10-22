<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Dados estruturados (JSON-LD) das paginas indexaveis (ADR-053).
 *
 * A home declara a organizacao e o site; a pagina de uma ferramenta declara o
 * SoftwareApplication e o caminho de navegacao (BreadcrumbList). Tudo sai do
 * servidor, junto com as metas do Blade: robo de busca nao executa JavaScript.
 *
 * Nada e emitido em pagina noindex - nem nas telas de acesso, nem na 404.
 */
final class StructuredData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forRequest(Request $request): array
    {
        if (Seo::noindex($request)) {
            return [];
        }

        return match ($request->route()?->getName()) {
            'home' => [
                self::organization(),
                self::website(),
            ],
            'tools.show' => self::tool($request),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) trans('app.brand'),
            'url' => self::homeUrl(),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => url((string) config('seo.image.file')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => (string) trans('app.brand'),
            'url' => self::homeUrl(),
            'inLanguage' => Locales::html(app()->getLocale()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tool(Request $request): array
    {
        $slug = Seo::toolSlug($request);

        if ($slug === null) {
            return [];
        }

        $name = (string) trans("tools.{$slug}.name");
        $homeUrl = self::homeUrl();
        $toolUrl = url()->current();

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => $name,
                'description' => (string) trans("tools.{$slug}.description"),
                'applicationCategory' => 'DeveloperApplication',
                'operatingSystem' => 'Any',
                'isAccessibleForFree' => true,
                'url' => $toolUrl,
                'inLanguage' => Locales::html(app()->getLocale()),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => (string) trans('app.nav.tools'),
                        'item' => $homeUrl,
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $name,
                        'item' => $toolUrl,
                    ],
                ],
            ],
        ];
    }

    /** A home no idioma da pagina, que e o canonical dela. */
    private static function homeUrl(): string
    {
        return url('/'.Locales::slug(app()->getLocale()));
    }
}
