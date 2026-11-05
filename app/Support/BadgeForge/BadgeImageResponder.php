<?php

namespace App\Support\BadgeForge;

use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resposta de imagem do BadgeForge (F1-F4).
 *
 * Ponto unico de Content-Type, Cache-Control, ETag/304, `download=1` e do
 * 503 sem Imagick. A rota estatica e a de provider usam o mesmo responder;
 * a badge pessoal reusa os headers com o corpo que tiver em disco.
 */
final class BadgeImageResponder
{
    public function __construct(
        private readonly PngRenderer $png,
        private readonly PngCache $pngCache,
    ) {}

    /**
     * @param  array<string, string>  $extraHeaders
     */
    public function respond(
        Request $request,
        BadgeSpec $spec,
        string $svg,
        string $extension,
        ?string $pngBody = null,
        array $extraHeaders = [],
    ): Response|JsonResponse {
        $ext = $extension === 'png' ? 'png' : 'svg';

        if ($ext === 'png' && ! $this->png->isSupported()) {
            Log::warning('badgeforge: extensao imagick ausente; PNG indisponivel.');

            return ApiError::response(
                'image_unavailable',
                'PNG rendering is unavailable.',
                503,
            );
        }

        $body = $ext === 'svg'
            ? $svg
            : ($pngBody ?? $this->pngCache->png($svg));

        $headers = [
            'Content-Type' => $ext === 'svg' ? 'image/svg+xml; charset=utf-8' : 'image/png',
            'Cache-Control' => 'public, max-age='.$spec->cacheSeconds,
        ] + $extraHeaders;

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
