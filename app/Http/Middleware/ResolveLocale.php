<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o idioma da requisicao.
 *
 * - Rotas com prefixo {locale} (paginas publicas e autenticacao): o slug da
 *   URL manda; a preferencia e persistida (coluna users.locale e cookie) para
 *   valer nas rotas sem prefixo.
 * - Demais rotas (dashboard, settings): usuario -> cookie -> Accept-Language
 *   -> fallback (docs/SPEC.md secao 3).
 */
class ResolveLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('locale');
        $localized = is_string($slug) ? Locales::fromSlug($slug) : null;

        if ($localized !== null) {
            $this->remember($request, $localized);
        } else {
            $localized = Locales::preferred($request);
        }

        app()->setLocale($localized);
        Carbon::setLocale(str_replace('_', '-', $localized));

        // Toda rota de autenticacao tem {locale} obrigatorio: o default faz
        // route('login') funcionar aqui dentro (redirect do middleware auth,
        // link do e-mail de reset, telas sem prefixo) sem repetir o parametro.
        URL::defaults(['locale' => Locales::slug($localized)]);

        return $next($request);
    }

    /**
     * Guarda a preferencia escolhida pela URL para as proximas visitas.
     */
    private function remember(Request $request, string $code): void
    {
        if ($request->cookie('locale') !== Locales::slug($code)) {
            Cookie::queue('locale', Locales::slug($code), 60 * 24 * 365);
        }

        $user = $request->user();

        if ($user !== null && $request->isMethod('GET') && $user->locale !== $code) {
            $user->forceFill(['locale' => $code])->save();
        }
    }
}
