<?php

namespace App\Http\Controllers\BadgeForge;

use App\Http\Controllers\Controller;
use App\Support\BadgeForge\BadgeRequest;
use App\Support\BadgeForge\StaticBadgeRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Badge estatica renderizada sob demanda (SPEC-BADGEFORGE §6).
 *
 * Entrada cosmetica invalida vira default dentro de `BadgeRequest`; so o
 * segmento vazio ou ilegivel chega aqui como 404. A resposta e cacheavel por
 * um dia por padrao e o ETag e o hash do proprio SVG - duas requisicoes
 * equivalentes produzem o mesmo hash.
 *
 * Nada do conteudo do badge vai para log: a URL ja carrega o texto e o log de
 * acesso pertence ao proxy, nao a aplicacao.
 */
final class StaticBadgeController extends Controller
{
    public function __invoke(
        Request $request,
        BadgeRequest $badges,
        StaticBadgeRenderer $renderer,
    ): Response {
        $label = $request->route('label');
        $message = $request->route('message');

        $spec = is_string($label) && is_string($message)
            ? $badges->spec($label, $message, $request->query())
            : null;

        if ($spec === null) {
            abort(404);
        }

        $svg = $renderer->render($spec);

        $response = new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age='.$spec->cacheSeconds,
        ]);

        // setEtag acrescenta as aspas; o hash vai cru. O isNotModified compara
        // de forma fraca e, em caso de match, troca para 304, esvazia o corpo
        // e remove o Content-Type - por isso os headers vem antes.
        $response->setEtag(hash('sha256', $svg));
        $response->isNotModified($request);

        return $response;
    }
}
