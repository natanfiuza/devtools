<?php

namespace App\Http\Controllers\Api\Colors;

use App\Actions\Colors\FavoritePalette;
use App\Actions\Colors\UnfavoritePalette;
use App\Http\Controllers\Controller;
use App\Models\ColorPalette;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Favoritar e desfavoritar paletas publicas (F5, ADR-128).
 *
 * So a propria conta aparece no vinculo; a paleta tem de continuar publica e
 * ativa, senao responde 404 como a pagina publica. A propria paleta nao entra
 * nos favoritos (403): ela ja esta em Minhas Paletas. As duas escritas sao
 * idempotentes e o cliente atualiza a estrela de forma otimista.
 */
class PaletteFavoriteController extends Controller
{
    public function store(
        Request $request,
        ColorPalette $colorPalette,
        FavoritePalette $favorite,
    ): JsonResponse {
        $favorite($this->account($request, $colorPalette), $colorPalette);

        return response()->json(['favorited' => true]);
    }

    public function destroy(
        Request $request,
        ColorPalette $colorPalette,
        UnfavoritePalette $unfavorite,
    ): JsonResponse {
        $unfavorite($this->account($request, $colorPalette), $colorPalette);

        return response()->json(['favorited' => false]);
    }

    private function account(Request $request, ColorPalette $palette): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        abort_unless(
            $palette->isPublic() && ! $palette->isArchived() && $palette->slug !== null,
            404,
        );

        // `abort(403)` vira `HttpException` generica, que o envelope da API nao
        // reconhece; a excecao concreta mantem `error.code = forbidden`.
        if ((int) $palette->user_id === (int) $user->getKey()) {
            throw new AccessDeniedHttpException;
        }

        return $user;
    }
}
