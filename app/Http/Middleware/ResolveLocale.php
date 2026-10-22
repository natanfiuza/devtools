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
 *   URL manda e vai para o cookie - e navegacao, nao preferencia.
 * - Demais rotas (dashboard, settings): cookie -> idioma da conta ->
 *   Accept-Language -> fallback (docs/SPEC.md secao 3).
 *
 * O idioma da conta (`users.locale`) e preferencia explicita: so muda quando a
 * pessoa muda, no cadastro ou no perfil. Navegar por /fr nao reescreve o idioma
 * dos e-mails de quem tem conta (ADR-026).
 */
class ResolveLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Rotas sem {locale} (fallback da 404) ainda podem estar sob um prefixo
        // de idioma: o primeiro segmento decide o idioma da pagina de erro.
        $slug = $request->route('locale') ?? $request->segment(1);
        $localized = is_string($slug) ? Locales::fromSlug($slug) : null;

        if ($localized !== null) {
            $this->rememberBrowsing($request, $localized);
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
     * Guarda no cookie o idioma que a pessoa acabou de escolher navegando.
     */
    private function rememberBrowsing(Request $request, string $code): void
    {
        if ($request->cookie('locale') !== Locales::slug($code)) {
            Cookie::queue('locale', Locales::slug($code), 60 * 24 * 365);
        }
    }
}
