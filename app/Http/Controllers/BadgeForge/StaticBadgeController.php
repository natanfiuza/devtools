<?php

namespace App\Http\Controllers\BadgeForge;

use App\Http\Controllers\Controller;
use App\Support\ApiError;
use App\Support\BadgeForge\BadgeRequest;
use App\Support\BadgeForge\BadgeSpec;
use App\Support\BadgeForge\PngCache;
use App\Support\BadgeForge\PngRenderer;
use App\Support\BadgeForge\StaticBadgeRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
 * resposta e 503 com o envelope `image_unavailable`.
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
        PngRenderer $png,
        PngCache $cache,
    ): Response|JsonResponse {
        $label = $request->route('label');
        $message = $request->route('message');

        $spec = is_string($label) && is_string($message)
            ? $badges->spec($label, $message, $request->query())
            : null;

        if ($spec === null) {
            abort(404);
        }

        $svg = $renderer->render($spec);
        $ext = $request->route('ext') === 'png' ? 'png' : 'svg';

        if ($ext === 'png' && ! $png->isSupported()) {
            Log::warning('badgeforge: extensao imagick ausente; PNG indisponivel.');

            return ApiError::response(
                'image_unavailable',
                'PNG rendering is unavailable.',
                503,
            );
        }

        $body = $ext === 'svg' ? $svg : $cache->png($svg);

        $headers = [
            'Content-Type' => $ext === 'svg' ? 'image/svg+xml; charset=utf-8' : 'image/png',
            'Cache-Control' => 'public, max-age='.$spec->cacheSeconds,
        ];

        if ($request->query('download') === '1') {
            $headers['Content-Disposition'] = self::attachment($spec, $ext);
        }

        $response = new Response($body, 200, $headers);

        // setEtag acrescenta as aspas; o hash vai cru. O isNotModified compara
        // de forma fraca e, em caso de match, troca para 304, esvazia o corpo
        // e remove o Content-Type - por isso os headers vem antes.
        $response->setEtag(hash('sha256', $body));
        $response->isNotModified($request);

        return $response;
    }

    /**
     * `attachment` com nome ASCII sanitizado e a variante UTF-8 do RFC 5987.
     * Controle, aspas e barra nunca chegam ao cabecalho.
     */
    private static function attachment(BadgeSpec $spec, string $ext): string
    {
        $raw = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $spec->label.'-'.$spec->message) ?? '';
        $raw = trim(mb_substr(trim($raw), 0, 80, 'UTF-8'));
        $raw = $raw === '' ? 'badge' : $raw;

        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii($raw)) ?? '';
        $ascii = trim($ascii, '-.');
        $ascii = $ascii === '' ? 'badge' : $ascii;

        return 'attachment; filename="'.$ascii.'.'.$ext.'"; filename*=UTF-8\'\''.rawurlencode($raw.'.'.$ext);
    }
}
