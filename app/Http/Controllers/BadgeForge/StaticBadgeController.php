<?php

namespace App\Http\Controllers\BadgeForge;

use App\Http\Controllers\Controller;
use App\Support\BadgeForge\BadgeImageResponder;
use App\Support\BadgeForge\BadgeRequest;
use App\Support\BadgeForge\StaticBadgeRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Badge estatica renderizada sob demanda (SPEC-BADGEFORGE §6).
 *
 * Entrada cosmetica invalida vira default dentro de `BadgeRequest`; so o
 * segmento vazio ou ilegivel chega aqui como 404. A resposta e cacheavel por
 * um dia por padrao e o ETag e o hash do proprio corpo - duas requisicoes
 * equivalentes produzem o mesmo hash.
 *
 * O SVG e o formato canonico; o PNG (F2) e rasterizado pelo Imagick e
 * guardado no cache em disco endereçado pelo sha256 do SVG. Sem Imagick a
 * resposta e 503 com o envelope `image_unavailable` - comportamento que vive
 * no `BadgeImageResponder`, compartilhado com a rota de provider (F4).
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
        BadgeImageResponder $responder,
    ): Response|JsonResponse {
        $label = $request->route('label');
        $message = $request->route('message');

        $spec = is_string($label) && is_string($message)
            ? $badges->spec($label, $message, $request->query())
            : null;

        if ($spec === null) {
            abort(404);
        }

        return $responder->respond(
            $request,
            $spec,
            $renderer->render($spec),
            (string) $request->route('ext'),
        );
    }
}
