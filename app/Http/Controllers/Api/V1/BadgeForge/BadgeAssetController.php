<?php

namespace App\Http\Controllers\Api\V1\BadgeForge;

use App\Actions\BadgeForge\CreateBadgeAsset;
use App\Actions\BadgeForge\DeleteBadgeAsset;
use App\Actions\BadgeForge\RefreshBadgeAsset;
use App\Actions\BadgeForge\SetBadgeAssetCredential;
use App\Actions\BadgeForge\UpdateBadgeAsset;
use App\Http\Controllers\Api\V1\Concerns\ResolvesApiIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\SetBadgeAssetCredentialRequest;
use App\Http\Requests\BadgeForge\StoreBadgeAssetRequest;
use App\Http\Requests\BadgeForge\UpdateBadgeAssetRequest;
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

    /**
     * Renomeacao (F9, ADR-090): a representacao mutavel e so o nome, como na
     * tela; provider, definicao, params e form ficam imutaveis.
     */
    public function update(
        UpdateBadgeAssetRequest $request,
        BadgeAsset $badgeAsset,
        UpdateBadgeAsset $update,
    ): JsonResponse {
        $this->ensureOwner($request, $badgeAsset);

        $asset = $update($this->apiUser($request), $badgeAsset, (string) $request->validated('name'));

        return response()->json(['data' => (new ApiBadgeAssetResource($asset))->toArray($request)]);
    }

    /**
     * Troca write-only do PAT (F9, ADR-090): o valor nunca e devolvido, so o
     * estado. A action mantem as regras de plano, provider privado e cifra.
     */
    public function credential(
        SetBadgeAssetCredentialRequest $request,
        BadgeAsset $badgeAsset,
        SetBadgeAssetCredential $setCredential,
    ): JsonResponse {
        $this->ensureOwner($request, $badgeAsset);

        $username = $request->validated('credential_username');

        $asset = $setCredential(
            $this->apiUser($request),
            $badgeAsset,
            (string) $request->validated('credential'),
            is_string($username) ? $username : null,
        );

        return response()->json(['data' => (new ApiBadgeAssetResource($asset))->toArray($request)]);
    }

    public function refresh(
        Request $request,
        BadgeAsset $badgeAsset,
        RefreshBadgeAsset $refresh,
    ): JsonResponse {
        $this->ensureOwner($request, $badgeAsset);

        // A action devolve o desenho, mas persiste no proprio model vinculado;
        // a resposta le os campos atualizados dele, como no JSON da tela.
        $refresh($this->apiUser($request), $badgeAsset);

        return response()->json([
            'data' => [
                'url' => $badgeAsset->publicSvgUrl(),
                'pngUrl' => $badgeAsset->publicPngUrl(),
                'generatedAt' => $badgeAsset->last_generated_at?->toIso8601String(),
            ],
        ]);
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
