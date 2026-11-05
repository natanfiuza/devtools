<?php

namespace App\Http\Controllers\BadgeForge;

use App\Enums\BadgeViewSubject;
use App\Http\Controllers\Controller;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeCustomProviderDefinition;
use App\Support\ApiError;
use App\Support\BadgeForge\Analytics\BadgeViewRecorder;
use App\Support\BadgeForge\BadgeImageResponder;
use App\Support\BadgeForge\Custom\CustomBadgeComposer;
use App\Support\BadgeForge\Custom\CustomMetricsResult;
use App\Support\BadgeForge\Custom\CustomPathParams;
use App\Support\BadgeForge\StaticBadgeRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Badge publica de um provider customizado (F7, ADR-087).
 *
 * Stateless e anonima: o ULID e a unica identidade e a URL de origem nunca sai
 * do servidor. Provider desabilitado, definicao inexistente e path fora do
 * contrato respondem 404; falha de guard/rede/pointer vira fallback 200, nunca
 * 422 ou 500 (diferente do endpoint da F5, que 422 numa URL guardada invalida).
 */
final class CustomProviderBadgeController extends Controller
{
    public function __invoke(
        Request $request,
        BadgeCustomProvider $customProvider,
        CustomBadgeComposer $composer,
        StaticBadgeRenderer $renderer,
        BadgeImageResponder $responder,
        BadgeViewRecorder $recorder,
    ): Response|JsonResponse {
        $extension = $request->route('ext');

        if (! is_string($extension) || ! in_array(strtolower($extension), ['svg', 'png'], true)) {
            return ApiError::response('not_found', 'Resource not found.', 404);
        }

        if (! $customProvider->enabled) {
            return ApiError::response('not_found', 'Resource not found.', 404);
        }

        $slug = $request->route('definition');
        $definition = is_string($slug)
            ? $customProvider->definitions()->where('slug', $slug)->first()
            : null;

        $path = $request->route('path');

        if (! $definition instanceof BadgeCustomProviderDefinition
            || ! is_string($path)
            || CustomPathParams::parse($path, $customProvider->pathParamNames()) === null) {
            return ApiError::response('not_found', 'Resource not found.', 404);
        }

        $badge = $composer->compose($customProvider, $definition, $path, $request->query());

        $headers = ['X-Badge-Cache' => $badge->status];

        if ($badge->status === CustomMetricsResult::STALE) {
            $headers['Warning'] = '110 - "Response is Stale"';
        }

        $response = $responder->respond(
            $request,
            $badge->spec,
            $renderer->render($badge->spec),
            strtolower($extension),
            null,
            $headers,
        );

        if (in_array($response->getStatusCode(), [200, 304], true)) {
            $recorder->record(
                $request,
                BadgeViewSubject::Custom,
                (string) $customProvider->getKey(),
                $customProvider->user,
                definition: (string) $definition->slug,
                cacheHit: $badge->status !== CustomMetricsResult::MISS,
                format: strtolower($extension),
                style: $badge->spec->style->id,
            );
        }

        return $response;
    }
}
