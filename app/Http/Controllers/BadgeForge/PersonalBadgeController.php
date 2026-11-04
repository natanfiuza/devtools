<?php

namespace App\Http\Controllers\BadgeForge;

use App\Http\Controllers\Controller;
use App\Models\BadgeAsset;
use App\Support\BadgeForge\BadgeImageResponder;
use App\Support\BadgeForge\Personal\PersonalBadgeRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Imagem publica de uma badge pessoal (F4, ADR-081).
 *
 * Stateless: sem sessao, sem CSRF, sem auth e sem `?refresh=1`. Resolve o
 * ULID junto do username exato do dono. Um 404 aqui e um corpo vazio direto,
 * nunca a pagina Inertia de erro: a URL e consumida por `<img>` e por READMEs.
 */
final class PersonalBadgeController extends Controller
{
    public function __invoke(
        Request $request,
        string $username,
        string $badgeAsset,
        PersonalBadgeRenderer $renderer,
        BadgeImageResponder $responder,
    ): Response|JsonResponse {
        try {
            $asset = BadgeAsset::query()
                ->with('user')
                ->whereKey($badgeAsset)
                ->whereHas('user', static fn (Builder $query): Builder => $query->where('username', $username))
                ->first();
        } catch (QueryException) {
            // Migracao ainda nao rodou ou banco fora: imagem responde 404.
            return response('', 404);
        }

        if ($asset === null) {
            return response('', 404);
        }

        $badge = $renderer->render($asset);

        $extension = strtolower((string) $request->route('ext')) === 'png' ? 'png' : 'svg';

        $headers = $badge->stale
            ? ['Warning' => '110 - "Response is Stale"']
            : [];

        return $responder->respond(
            $request,
            $badge->spec,
            $badge->svg,
            $extension,
            $extension === 'png' ? $badge->png : null,
            $headers,
        );
    }
}
