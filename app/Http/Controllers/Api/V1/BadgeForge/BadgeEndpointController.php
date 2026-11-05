<?php

namespace App\Http\Controllers\Api\V1\BadgeForge;

use App\Actions\BadgeForge\CreateBadgeEndpoint;
use App\Actions\BadgeForge\DeleteBadgeEndpoint;
use App\Actions\BadgeForge\UpdateBadgeEndpoint;
use App\Http\Controllers\Api\V1\Concerns\ResolvesApiIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\StoreBadgeEndpointRequest;
use App\Http\Requests\BadgeForge\UpdateBadgeEndpointRequest;
use App\Http\Resources\ApiBadgeEndpointResource;
use App\Models\BadgeEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints JSON do dono (F9, ADR-090).
 *
 * Espelha a tela: Free cadastra, a URL de origem e imutavel e o probe/SSRF
 * continuam nas actions. A URL nunca e devolvida por esta API - a imagem
 * publica e identificada so pelo ULID. Recurso de outra conta responde 404.
 */
final class BadgeEndpointController extends Controller
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

        $endpoints = $user->badgeEndpoints()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $endpoints->getCollection()
                ->map(fn (BadgeEndpoint $endpoint): array => (new ApiBadgeEndpointResource($endpoint))->toArray($request))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $endpoints->currentPage(),
                'per_page' => $endpoints->perPage(),
                'total' => $endpoints->total(),
                'last_page' => $endpoints->lastPage(),
            ],
            'links' => [
                'next' => $endpoints->nextPageUrl(),
                'previous' => $endpoints->previousPageUrl(),
            ],
        ]);
    }

    /**
     * O probe e o guard de SSRF ficam na action; um payload fora do contrato
     * ou uma URL insegura respondem 422 no envelope, como na tela.
     */
    public function store(StoreBadgeEndpointRequest $request, CreateBadgeEndpoint $create): JsonResponse
    {
        $endpoint = $create($this->apiUser($request), $request->validated());

        return response()->json(
            ['data' => (new ApiBadgeEndpointResource($endpoint))->toArray($request)],
            201,
        );
    }

    public function update(
        UpdateBadgeEndpointRequest $request,
        BadgeEndpoint $badgeEndpoint,
        UpdateBadgeEndpoint $update,
    ): JsonResponse {
        $this->ensureOwner($request, $badgeEndpoint);

        $endpoint = $update($badgeEndpoint, (string) $request->validated('name'));

        return response()->json(['data' => (new ApiBadgeEndpointResource($endpoint))->toArray($request)]);
    }

    public function destroy(
        Request $request,
        BadgeEndpoint $badgeEndpoint,
        DeleteBadgeEndpoint $delete,
    ): JsonResponse {
        $this->ensureOwner($request, $badgeEndpoint);

        $id = (string) $badgeEndpoint->getKey();
        $delete($badgeEndpoint);

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    private function ensureOwner(Request $request, BadgeEndpoint $endpoint): void
    {
        abort_unless(
            (int) $endpoint->user_id === (int) $this->apiUser($request)->getKey(),
            404,
        );
    }
}
