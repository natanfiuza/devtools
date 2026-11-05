<?php

namespace App\Http\Controllers\BadgeForge;

use App\Enums\BadgeViewSubject;
use App\Http\Controllers\Controller;
use App\Models\BadgeAsset;
use App\Support\ApiError;
use App\Support\BadgeForge\Analytics\BadgeViewRecorder;
use App\Support\BadgeForge\BadgeImageResponder;
use App\Support\BadgeForge\Personal\PersonalBadgeRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Imagem publica de uma badge pessoal (F4, ADR-081).
 *
 * Stateless: sem sessao, sem CSRF, sem auth e sem `?refresh=1`. Resolve o
 * ULID junto do username exato do dono. Recurso inexistente responde 404 no
 * envelope `ApiError` e falha de banco sobe para o handler global, que a
 * converte em 503 (F8, ADR-089) - nunca a pagina Inertia de erro.
 */
final class PersonalBadgeController extends Controller
{
    public function __invoke(
        Request $request,
        string $username,
        string $badgeAsset,
        PersonalBadgeRenderer $renderer,
        BadgeImageResponder $responder,
        BadgeViewRecorder $recorder,
    ): Response|JsonResponse {
        $asset = BadgeAsset::query()
            ->with('user')
            ->whereKey($badgeAsset)
            ->whereHas('user', static fn (Builder $query): Builder => $query->where('username', $username))
            ->first();

        if ($asset === null) {
            return ApiError::response('not_found', 'Resource not found.', 404);
        }

        $badge = $renderer->render($asset);

        $extension = strtolower((string) $request->route('ext')) === 'png' ? 'png' : 'svg';

        $headers = $badge->stale
            ? ['Warning' => '110 - "Response is Stale"']
            : [];

        $response = $responder->respond(
            $request,
            $badge->spec,
            $badge->svg,
            $extension,
            $extension === 'png' ? $badge->png : null,
            $headers,
        );

        // Analytics so depois do responder: um 503 (sem Imagick) nao conta.
        if (in_array($response->getStatusCode(), [200, 304], true)) {
            $recorder->record(
                $request,
                BadgeViewSubject::Personal,
                (string) $asset->getKey(),
                $asset->user,
                format: $extension,
                style: $badge->spec->style->id,
            );
        }

        return $response;
    }
}
