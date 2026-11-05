<?php

namespace App\Http\Controllers\BadgeForge;

use App\Enums\BadgeViewSubject;
use App\Http\Controllers\Controller;
use App\Models\BadgeEndpoint;
use App\Support\ApiError;
use App\Support\BadgeForge\Analytics\BadgeViewRecorder;
use App\Support\BadgeForge\BadgeImageResponder;
use App\Support\BadgeForge\Endpoint\EndpointBadgeComposer;
use App\Support\BadgeForge\Endpoint\EndpointMetricsResult;
use App\Support\BadgeForge\Endpoint\EndpointUrlException;
use App\Support\BadgeForge\StaticBadgeRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Badge publica de endpoint JSON (F5, ADR-083).
 *
 * Stateless e anonima, como provider e badge pessoal: o ULID e a unica
 * identidade, a URL de origem nunca sai do servidor. ULID desconhecido
 * responde 404 pelo envelope; URL guardada invalida responde 422
 * `unsafe_url`; falha de origem vira stale/fallback 200, nunca 500.
 */
final class EndpointBadgeController extends Controller
{
    public function __invoke(
        Request $request,
        BadgeEndpoint $badgeEndpoint,
        EndpointBadgeComposer $composer,
        StaticBadgeRenderer $renderer,
        BadgeImageResponder $responder,
        BadgeViewRecorder $recorder,
    ): Response|JsonResponse {
        $extension = $request->route('ext');

        if (! is_string($extension) || ! in_array(strtolower($extension), ['svg', 'png'], true)) {
            return ApiError::response('not_found', 'Resource not found.', 404);
        }

        try {
            $badge = $composer->compose($badgeEndpoint, $request->query());
        } catch (EndpointUrlException) {
            return ApiError::response(
                'unsafe_url',
                'The endpoint URL is not allowed.',
                422,
            );
        }

        $headers = ['X-Badge-Cache' => $badge->status];

        if ($badge->status === EndpointMetricsResult::STALE) {
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
                BadgeViewSubject::Endpoint,
                (string) $badgeEndpoint->getKey(),
                $badgeEndpoint->user,
                cacheHit: $badge->status !== EndpointMetricsResult::MISS,
                format: strtolower($extension),
                style: $badge->spec->style->id,
            );
        }

        return $response;
    }
}
