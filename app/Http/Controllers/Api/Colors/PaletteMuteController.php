<?php

namespace App\Http\Controllers\Api\Colors;

use App\Http\Controllers\Controller;
use App\Models\ColorPalette;
use App\Models\ColorPaletteMute;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Silenciar a propria paleta (F7, ADR-142).
 *
 * So o dono silencia, e o silencio vale para o que acontece naquela paleta:
 * curtidas, comentarios e respostas. Seguidores nao entram, porque seguir e
 * uma relacao de conta. A escrita e idempotente: repetir o POST nao cria uma
 * segunda linha, e o DELETE sem linha tambem responde com sucesso.
 */
class PaletteMuteController extends Controller
{
    public function store(Request $request, ColorPalette $colorPalette): JsonResponse
    {
        [$user] = $this->authorizeOwner($request, $colorPalette);

        ColorPaletteMute::query()->firstOrCreate([
            'color_palette_id' => $colorPalette->id,
            'user_id' => $user->getKey(),
        ]);

        return response()->json(['muted' => true]);
    }

    public function destroy(Request $request, ColorPalette $colorPalette): JsonResponse
    {
        [$user] = $this->authorizeOwner($request, $colorPalette);

        ColorPaletteMute::query()
            ->where('color_palette_id', $colorPalette->id)
            ->where('user_id', $user->getKey())
            ->delete();

        return response()->json(['muted' => false]);
    }

    /**
     * @return array{0: User}
     */
    private function authorizeOwner(Request $request, ColorPalette $palette): array
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);
        abort_unless(
            $palette->isPublic() && ! $palette->isArchived() && $palette->slug !== null,
            404,
        );

        // Paleta de outra conta e dono inexistente caem no mesmo 404: o
        // endpoint nao revela a existencia do recurso para quem nao o possui.
        abort_unless((int) $palette->user_id === (int) $user->getKey(), 404);

        return [$user];
    }
}
