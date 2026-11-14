<?php

namespace App\Http\Controllers\Api\Colors;

use App\Actions\Colors\LikePalette;
use App\Actions\Colors\UnlikePalette;
use App\Http\Controllers\Controller;
use App\Models\ColorPalette;
use App\Models\User;
use App\Support\Colors\AnonymousIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Curtir e descurtir paletas publicas (F6, ADR-135).
 *
 * A conta curte pelo proprio login; o visitante curte por um identificador
 * first-party, criado apenas quando o primeiro like conclui. A paleta precisa
 * estar publica, ativa e com slug, senao responde 404 como a pagina publica. O
 * dono nao curte a propria paleta (403). A resposta nao carrega identificador
 * algum: so o estado e a contagem de linhas ativas.
 */
class PaletteLikeController extends Controller
{
    public function store(
        Request $request,
        ColorPalette $colorPalette,
        LikePalette $like,
    ): JsonResponse {
        $this->authorizePalette($request, $colorPalette);

        $user = $request->user();

        if ($user instanceof User) {
            $like($colorPalette, $user, null);

            return response()->json($this->payload($colorPalette));
        }

        $anonymousId = AnonymousIdentity::read($request);
        $issued = $anonymousId === null;
        $anonymousId ??= AnonymousIdentity::issue();

        $like($colorPalette, null, $anonymousId);

        // O cookie so nasce quando a curtida conclui; um POST que falha na
        // visibilidade nao deixa identificador para tras.
        if ($issued) {
            Cookie::queue(AnonymousIdentity::cookie($anonymousId));
        }

        return response()->json($this->payload($colorPalette));
    }

    public function destroy(
        Request $request,
        ColorPalette $colorPalette,
        UnlikePalette $unlike,
    ): JsonResponse {
        $this->authorizePalette($request, $colorPalette);

        $user = $request->user();

        if ($user instanceof User) {
            $unlike($colorPalette, $user, null);

            return response()->json($this->payload($colorPalette, false));
        }

        // Sem cookie nao ha curtida anonima para desfazer; a resposta segue
        // idempotente e nenhum identificador novo e emitido aqui.
        $anonymousId = AnonymousIdentity::read($request);

        if ($anonymousId !== null) {
            $unlike($colorPalette, null, $anonymousId);
        }

        return response()->json($this->payload($colorPalette, false));
    }

    private function authorizePalette(Request $request, ColorPalette $palette): void
    {
        abort_unless(
            $palette->isPublic() && ! $palette->isArchived() && $palette->slug !== null,
            404,
        );

        $user = $request->user();

        if ($user instanceof User && (int) $palette->user_id === (int) $user->getKey()) {
            throw new AccessDeniedHttpException;
        }
    }

    /**
     * @return array{liked: bool, likes_count: int}
     */
    private function payload(ColorPalette $palette, bool $liked = true): array
    {
        return [
            'liked' => $liked,
            'likes_count' => $palette->likes()->active()->count(),
        ];
    }
}
