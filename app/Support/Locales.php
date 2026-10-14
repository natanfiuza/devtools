<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolve os idiomas suportados (config/locales.php) e a preferencia do
 * visitante. Ver docs/SPEC.md secao 3.
 */
final class Locales
{
    /**
     * Todos os idiomas: codigo => ['slug' => ..., 'html' => ..., 'dir' => ...].
     *
     * @return array<string, array{slug: string, html: string, dir: string}>
     */
    public static function all(): array
    {
        /** @var array<string, array{slug: string, html: string, dir: string}> $supported */
        $supported = config('locales.supported', []);

        return $supported;
    }

    /**
     * Codigos de idioma (chaves dos diretorios em lang/).
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /**
     * Slugs usados nas URLs.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_values(array_map(fn (array $locale): string => $locale['slug'], self::all()));
    }

    public static function fallback(): string
    {
        $fallback = config('locales.fallback', 'en');

        return is_string($fallback) && array_key_exists($fallback, self::all())
            ? $fallback
            : array_key_first(self::all()) ?? 'en';
    }

    /**
     * Slug da URL para um codigo de idioma.
     */
    public static function slug(string $code): string
    {
        return self::all()[$code]['slug'] ?? self::all()[self::fallback()]['slug'];
    }

    /**
     * Codigo de idioma para um slug da URL.
     */
    public static function fromSlug(?string $slug): ?string
    {
        foreach (self::all() as $code => $locale) {
            if ($locale['slug'] === $slug) {
                return $code;
            }
        }

        return null;
    }

    public static function isSupported(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::all());
    }

    /**
     * Valor para o atributo lang do <html>.
     */
    public static function html(?string $code = null): string
    {
        $code = self::isSupported($code) ? (string) $code : self::fallback();

        return self::all()[$code]['html'];
    }

    /**
     * Idioma de navegacao do visitante, na ordem: cookie, idioma da conta,
     * Accept-Language, fallback.
     *
     * O cookie vem na frente porque e a escolha mais recente e mais local - a
     * pessoa acabou de abrir /fr neste navegador. O idioma da conta e a
     * preferencia duradoura (a dos e-mails), que so muda quando a pessoa muda
     * no cadastro ou no perfil (ADR-026).
     */
    public static function preferred(Request $request): string
    {
        // O cookie e escrito pelo JS com o slug da URL (pt-br), nao com o
        // codigo do idioma (pt_BR).
        $stored = $request->cookie('locale');
        $cookie = self::fromSlug(is_string($stored) ? $stored : null);

        if ($cookie !== null) {
            return $cookie;
        }

        $user = $request->user();
        $account = $user?->locale;

        if (is_string($account) && self::isSupported($account)) {
            return $account;
        }

        // O fallback vai na frente: sem nenhum slug em comum, getPreferredLanguage
        // devolve o primeiro da lista, e nao um valor nulo.
        $available = array_values(array_unique([self::slug(self::fallback()), ...self::slugs()]));

        return $request->getPreferredLanguage($available) ?? self::slug(self::fallback());
    }

    /**
     * URLs desta mesma pagina em todos os idiomas (atributo hreflang).
     * Vazio quando a rota nao tem prefixo de idioma: o painel e as rotas de
     * maquina (`auth/{provider}/...`) ficam fora do prefixo (ADR-022). Nas
     * telas de acesso, que tem prefixo mas pedem `noindex`, quem corta as
     * alternates e a view.
     *
     * @return array<string, string> html => url
     */
    public static function alternates(Request $request): array
    {
        $slug = $request->route('locale');
        $current = self::fromSlug(is_string($slug) ? $slug : null);

        if ($current === null) {
            return [];
        }

        $segments = explode('/', trim($request->path(), '/'));
        $query = $request->getQueryString();
        $suffix = $query === null ? '' : '?'.$query;

        $urls = [];

        foreach (self::all() as $locale) {
            $segments[0] = $locale['slug'];

            $urls[$locale['html']] = url(implode('/', $segments).$suffix);
        }

        return $urls;
    }

    /**
     * Idiomas como lista pronta para o frontend (com endonimo do idioma ativo).
     *
     * @return list<array{code: string, slug: string, html: string, dir: string, name: string}>
     */
    public static function forFrontend(string $activeLocale): array
    {
        $names = trans('locales');
        $names = is_array($names) ? $names : [];

        $locales = [];

        foreach (self::all() as $code => $locale) {
            $locales[] = [
                'code' => $code,
                'slug' => $locale['slug'],
                'html' => $locale['html'],
                'dir' => $locale['dir'],
                'name' => (string) ($names[$code] ?? $code),
            ];
        }

        return $locales;
    }
}
