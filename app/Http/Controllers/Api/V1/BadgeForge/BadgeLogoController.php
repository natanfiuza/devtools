<?php

namespace App\Http\Controllers\Api\V1\BadgeForge;

use App\Actions\BadgeForge\ParseCustomLogoUpload;
use App\Actions\BadgeForge\StoreCustomLogo;
use App\Http\Controllers\Api\V1\Concerns\ResolvesApiIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\StoreCustomLogoRequest;
use App\Http\Resources\ApiBadgeLogoResource;
use App\Models\BadgeCustomLogo;
use App\Support\BadgeForge\BadgeForgeQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Logos personalizados do dono (F8, ADR-088).
 *
 * So metadados: o SVG normalizado (viewBox + paths) nunca sai por esta API,
 * nem para o dono do token. Leitura exige `badgeforge:read`; escrita exige
 * `badgeforge:write`, sempre no grupo de rotas. Logo de outra conta responde
 * 404 (`not_found`), nunca 403, como na tela.
 */
final class BadgeLogoController extends Controller
{
    use ResolvesApiIdentity;

    public function index(Request $request, BadgeForgeQuota $quota): JsonResponse
    {
        $user = $this->apiUser($request);

        $logos = $user->badgeCustomLogos()
            ->get(['id', 'name', 'view_box', 'path_count', 'size_bytes', 'created_at'])
            ->sort(static fn (BadgeCustomLogo $a, BadgeCustomLogo $b): int => strcasecmp((string) $a->name, (string) $b->name)
                ?: strcmp((string) $a->id, (string) $b->id))
            ->values();

        return response()->json([
            'data' => $logos
                ->map(fn (BadgeCustomLogo $logo): array => (new ApiBadgeLogoResource($logo))->toArray($request))
                ->all(),
            'meta' => [
                'limit' => $quota->customLogoLimit($user),
                'used' => $logos->count(),
            ],
        ]);
    }

    public function store(
        StoreCustomLogoRequest $request,
        ParseCustomLogoUpload $parse,
        StoreCustomLogo $store,
    ): JsonResponse {
        $logo = $store(
            $this->apiUser($request),
            (string) $request->validated('name'),
            $parse($request->file('logo')),
        );

        return response()->json(
            ['data' => (new ApiBadgeLogoResource($logo))->toArray($request)],
            201,
        );
    }

    public function destroy(Request $request, BadgeCustomLogo $logo): JsonResponse
    {
        $this->ensureOwner($request, $logo);

        $id = (string) $logo->getKey();
        $logo->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    private function ensureOwner(Request $request, BadgeCustomLogo $logo): void
    {
        abort_unless(
            (int) $logo->user_id === (int) $this->apiUser($request)->getKey(),
            404,
        );
    }
}
