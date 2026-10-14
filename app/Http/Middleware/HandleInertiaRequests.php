<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Appearance;
use App\Support\Impersonation;
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
        $user = $request->user();
        // Quem esta por tras da sessao, quando ela e uma impersonation. Vai
        // como prop porque a faixa de aviso aparece em toda tela autenticada
        // (ADR-025) - e nulo no caso normal.
        $impersonator = $user instanceof User ? Impersonation::actor($request) : null;

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
                'user' => $user,
                // O papel cru nao vai para o frontend (esta no #[Hidden] do
                // modelo): o que as telas decidem e "pode ver o painel?".
                'isAdmin' => $user instanceof User && $user->isAdmin(),
            ],
            'impersonation' => $impersonator === null
                ? null
                : ['email' => $impersonator->email],
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
