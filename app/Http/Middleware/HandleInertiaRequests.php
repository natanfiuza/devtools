<?php

namespace App\Http\Middleware;

use App\Support\Appearance;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => $locale,
            'localeSlug' => Locales::slug($locale),
            'localeHtml' => Locales::html($locale),
            'locales' => Locales::forFrontend($locale),
            'translations' => $this->translations($locale),
            'appearance' => Appearance::resolve($request),
            'seo' => [
                'noindex' => Seo::noindex($request),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Mensagens do idioma ativo, escopadas por namespace.
     *
     * @return array<string, array<string, mixed>>
     */
    private function translations(string $locale): array
    {
        $messages = [];

        foreach ((array) config('locales.namespaces', []) as $namespace) {
            $path = lang_path($locale.'/'.$namespace.'.php');

            if (! is_file($path)) {
                continue;
            }

            $contents = require $path;

            $messages[(string) $namespace] = is_array($contents) ? $contents : [];
        }

        return $messages;
    }
}
