<?php

namespace App\Http\Controllers\Api\V1\BadgeForge;

use App\Actions\BadgeForge\CreateBadgeAsset;
use App\Actions\BadgeForge\DeleteBadgeAsset;
use App\Http\Controllers\Api\V1\Concerns\ResolvesApiIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\StoreBadgeAssetRequest;
use App\Http\Resources\ApiBadgeAssetResource;
use App\Models\BadgeAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Badges pessoais do dono (F8, ADR-088).
 *
 * A lista sobrevive ao downgrade: o dono continua vendo e podendo excluir o
 * que criou. A credencial nunca e selecionada; so o estado dela viaja.
 */
final class BadgeAssetController extends Controller
{
    use ResolvesApiIdentity;

    public function index(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        /** @var array{per_page?: int} $filters */
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? max(1, min(100, (int) config('api.pagination.per_page', 20))));

        $assets = $user->badgeAssets()
            ->with('user:id,username')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $assets->getCollection()
                ->map(fn (BadgeAsset $asset): array => (new ApiBadgeAssetResource($asset))->toArray($request))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'last_page' => $assets->lastPage(),
            ],
            'links' => [
                'next' => $assets->nextPageUrl(),
                'previous' => $assets->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Criacao pela API: as regras de plano, username, provider e credencial
     * continuam nas actions - a API so troca o `back()` da tela por um 201.
     */
    public function store(StoreBadgeAssetRequest $request, CreateBadgeAsset $create): JsonResponse
    {
        $asset = $create($this->apiUser($request), $request->validated());

        return response()->json(
            ['data' => (new ApiBadgeAssetResource($asset))->toArray($request)],
            201,
        );
    }

    public function destroy(Request $request, BadgeAsset $badgeAsset, DeleteBadgeAsset $delete): JsonResponse
    {
        $this->ensureOwner($request, $badgeAsset);

        $id = (string) $badgeAsset->getKey();
        $delete($badgeAsset);

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    private function ensureOwner(Request $request, BadgeAsset $asset): void
    {
        abort_unless(
            (int) $asset->user_id === (int) $this->apiUser($request)->getKey(),
            404,
        );
    }
}
