<?php

namespace App\Http\Controllers\Api\V1\BadgeForge;

use App\Actions\BadgeForge\CreateBadgeCustomProvider;
use App\Actions\BadgeForge\DeleteBadgeCustomProvider;
use App\Actions\BadgeForge\UpdateBadgeCustomProvider;
use App\Http\Controllers\Api\V1\Concerns\ResolvesApiIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\StoreBadgeCustomProviderRequest;
use App\Http\Requests\BadgeForge\UpdateBadgeCustomProviderRequest;
use App\Http\Resources\ApiBadgeCustomProviderResource;
use App\Models\BadgeCustomProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Providers customizados do dono (F9, ADR-090).
 *
 * Espelha a tela: criar e editar sao pagas (middleware `badgeforge.paid` nas
 * rotas), excluir continua liberado depois de um downgrade e a leitura fica
 * no Free. `url_template`, `path_params` e `example_path` sao imutaveis e a
 * origem nunca e devolvida. Recurso de outra conta responde 404.
 */
final class BadgeCustomProviderController extends Controller
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

        $providers = $user->badgeCustomProviders()
            ->with('definitions')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $providers->getCollection()
                ->map(fn (BadgeCustomProvider $provider): array => (new ApiBadgeCustomProviderResource($provider))->toArray($request))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $providers->currentPage(),
                'per_page' => $providers->perPage(),
                'total' => $providers->total(),
                'last_page' => $providers->lastPage(),
            ],
            'links' => [
                'next' => $providers->nextPageUrl(),
                'previous' => $providers->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Limite de plano, placeholders, JSON Pointers e probe ficam na action: o
     * shape invalido responde 422 no envelope, como na tela.
     */
    public function store(StoreBadgeCustomProviderRequest $request, CreateBadgeCustomProvider $create): JsonResponse
    {
        $provider = $create($this->apiUser($request), $request->validated());

        return response()->json(
            ['data' => (new ApiBadgeCustomProviderResource($provider))->toArray($request)],
            201,
        );
    }

    /**
     * O update so muda nome e metricas: o conjunto de slugs e fixo e
     * `url_template` e proibido pelo FormRequest. O re-probe e o bump de
     * `metrics_version` ficam na action.
     */
    public function update(
        UpdateBadgeCustomProviderRequest $request,
        BadgeCustomProvider $badgeCustomProvider,
        UpdateBadgeCustomProvider $update,
    ): JsonResponse {
        $this->ensureOwner($request, $badgeCustomProvider);

        $provider = $update($badgeCustomProvider, $request->validated());

        return response()->json(['data' => (new ApiBadgeCustomProviderResource($provider))->toArray($request)]);
    }

    public function destroy(
        Request $request,
        BadgeCustomProvider $badgeCustomProvider,
        DeleteBadgeCustomProvider $delete,
    ): JsonResponse {
        $this->ensureOwner($request, $badgeCustomProvider);

        $id = (string) $badgeCustomProvider->getKey();
        $delete($badgeCustomProvider);

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    private function ensureOwner(Request $request, BadgeCustomProvider $provider): void
    {
        abort_unless(
            (int) $provider->user_id === (int) $this->apiUser($request)->getKey(),
            404,
        );
    }
}
