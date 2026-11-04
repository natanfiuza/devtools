<?php

namespace App\Http\Controllers\BadgeForge;

use App\Http\Controllers\Controller;
use App\Support\ApiError;
use App\Support\BadgeForge\BadgeImageResponder;
use App\Support\BadgeForge\Providers\ProviderBadgeComposer;
use App\Support\BadgeForge\Providers\ProviderMetricsResult;
use App\Support\BadgeForge\StaticBadgeRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Badge publica de provider (F4, ADR-080).
 *
 * Stateless e anonima: nenhum token global, sem sessao e sem `?refresh=1`.
 * Provider/definicao desconhecido, desabilitado, path fora do pattern ou URL
 * insegura responde 404 no envelope `ApiError`; falha do provider vira
 * stale/fallback 200, nunca 500.
 */
final class ProviderBadgeController extends Controller
{
    public function __invoke(
        Request $request,
        ProviderBadgeComposer $composer,
        StaticBadgeRenderer $renderer,
        BadgeImageResponder $responder,
    ): Response|JsonResponse {
        $provider = $request->route('provider');
        $definition = $request->route('definition');
        $rawPath = $request->route('path');

        if (! is_string($provider) || ! is_string($definition) || ! is_string($rawPath)) {
            return self::notFound();
        }

        if (preg_match('/\A(.+)\.(svg|png)\z/i', $rawPath, $matches) !== 1) {
            return self::notFound();
        }

        $badge = $composer->compose($provider, $definition, $matches[1], $request->query());

        if ($badge === null) {
            return self::notFound();
        }

        $headers = ['X-Badge-Cache' => $badge->status];

        if ($badge->status === ProviderMetricsResult::STALE) {
            $headers['Warning'] = '110 - "Response is Stale"';
        }

        return $responder->respond(
            $request,
            $badge->spec,
            $renderer->render($badge->spec),
            strtolower($matches[2]),
            null,
            $headers,
        );
    }

    private static function notFound(): JsonResponse
    {
        return ApiError::response('not_found', 'Resource not found.', 404);
    }
}
