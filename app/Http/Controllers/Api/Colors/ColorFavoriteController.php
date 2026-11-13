<?php

namespace App\Http\Controllers\Api\Colors;

use App\Actions\Colors\FavoriteColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\ColorFavoriteRequest;
use App\Http\Requests\Colors\DeleteColorFavoriteRequest;
use App\Models\ColorFavorite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cores favoritas da conta (F5, ADR-127).
 *
 * A lista e a mesma nas duas tools; antes cada navegador tinha a sua. A
 * escrita e idempotente: favoritar/desfavoritar duas vezes nao e erro.
 */
class ColorFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = ColorFavorite::query()
            ->forUser($this->user($request)->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) config('colors.library.favorites_per_page', 100));

        return response()->json([
            'data' => $favorites->getCollection()
                ->map(static fn (ColorFavorite $favorite): array => [
                    'id' => $favorite->id,
                    'color' => $favorite->color,
                    'created_at' => $favorite->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'next_cursor' => $favorites->nextCursor()?->encode(),
        ]);
    }

    public function store(
        ColorFavoriteRequest $request,
        FavoriteColor $favorite,
    ): JsonResponse {
        $entry = $favorite($this->user($request), (string) $request->validated('color'));

        return response()->json([
            'favorited' => true,
            'entry' => [
                'id' => $entry->id,
                'color' => $entry->color,
                'created_at' => $entry->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(DeleteColorFavoriteRequest $request): JsonResponse
    {
        $color = $request->validated('color');

        $query = ColorFavorite::query()->forUser($this->user($request)->getKey());

        if (is_string($color)) {
            $query->where('color', $color);
        }

        $query->delete();

        return response()->json(['favorited' => false]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
