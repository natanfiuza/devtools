<?php

namespace App\Http\Controllers\Api\Colors;

use App\Actions\Colors\ImportColorHistory;
use App\Actions\Colors\RecordColorHistory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\DeleteColorHistoryRequest;
use App\Http\Requests\Colors\ImportColorHistoryRequest;
use App\Http\Requests\Colors\StoreColorHistoryRequest;
use App\Models\ColorHistoryEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Historico de cores da conta (F5, ADR-127).
 *
 * Endpoints JSON consumidos pelo navegador; o anonimo nao usa esta API,
 * continua no `localStorage` (ADR-118). A listagem e paginada por cursor
 * porque o historico nao tem cota funcional.
 */
class ColorHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $entries = ColorHistoryEntry::query()
            ->forUser($this->user($request)->getKey())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) config('colors.library.history_per_page', 100));

        return response()->json([
            'data' => $entries->getCollection()
                ->map(static fn (ColorHistoryEntry $entry): array => [
                    'id' => $entry->id,
                    'color' => $entry->color,
                    'updated_at' => $entry->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'next_cursor' => $entries->nextCursor()?->encode(),
        ]);
    }

    public function store(
        StoreColorHistoryRequest $request,
        RecordColorHistory $record,
    ): JsonResponse {
        $entry = $record($this->user($request), (string) $request->validated('color'));

        return response()->json([
            'entry' => [
                'id' => $entry->id,
                'color' => $entry->color,
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function import(
        ImportColorHistoryRequest $request,
        ImportColorHistory $import,
    ): JsonResponse {
        /** @var list<string> $colors */
        $colors = $request->validated('colors');

        return response()->json([
            'imported' => $import($this->user($request), $colors),
            'total' => count($colors),
        ]);
    }

    public function destroy(DeleteColorHistoryRequest $request): JsonResponse
    {
        $color = $request->validated('color');

        $query = ColorHistoryEntry::query()->forUser($this->user($request)->getKey());

        if (is_string($color)) {
            $query->where('color', $color);
        }

        $query->delete();

        return response()->json(['deleted' => true]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
