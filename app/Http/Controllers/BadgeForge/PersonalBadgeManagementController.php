<?php

namespace App\Http\Controllers\BadgeForge;

use App\Actions\BadgeForge\CreateBadgeAsset;
use App\Actions\BadgeForge\DeleteBadgeAsset;
use App\Actions\BadgeForge\RefreshBadgeAsset;
use App\Actions\BadgeForge\SetBadgeAssetCredential;
use App\Actions\BadgeForge\UpdateBadgeAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\SetBadgeAssetCredentialRequest;
use App\Http\Requests\BadgeForge\StoreBadgeAssetRequest;
use App\Http\Requests\BadgeForge\UpdateBadgeAssetRequest;
use App\Models\BadgeAsset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Gestao das badges pessoais (F4, ADR-081).
 *
 * A imagem publica (`/b/{username}/{ulid}`) e stateless e nao passa por aqui.
 * Estas rotas sao as acoes explicitas do dono; recurso de outra conta
 * responde 404 e nunca revela o ULID. Free continua vendo e excluindo as
 * badges que sobreviveram a um downgrade, mas criar/editar/refresh exige
 * plano pago (422 `paid_only`).
 */
class PersonalBadgeManagementController extends Controller
{
    public function store(
        StoreBadgeAssetRequest $request,
        CreateBadgeAsset $create,
    ): RedirectResponse {
        $create($this->user($request), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.badge_created'),
        ]);

        return back();
    }

    public function update(
        UpdateBadgeAssetRequest $request,
        BadgeAsset $badgeAsset,
        UpdateBadgeAsset $update,
    ): RedirectResponse {
        $this->ensureOwner($request, $badgeAsset);

        $update($this->user($request), $badgeAsset, (string) $request->validated('name'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.badge_updated'),
        ]);

        return back();
    }

    public function credential(
        SetBadgeAssetCredentialRequest $request,
        BadgeAsset $badgeAsset,
        SetBadgeAssetCredential $setCredential,
    ): RedirectResponse {
        $this->ensureOwner($request, $badgeAsset);

        $username = $request->validated('credential_username');
        $setCredential(
            $this->user($request),
            $badgeAsset,
            (string) $request->validated('credential'),
            is_string($username) ? $username : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.badge_credential_updated'),
        ]);

        return back();
    }

    public function refresh(
        Request $request,
        BadgeAsset $badgeAsset,
        RefreshBadgeAsset $refresh,
    ): JsonResponse {
        $this->ensureOwner($request, $badgeAsset);

        $refresh($this->user($request), $badgeAsset);

        return response()->json([
            'url' => $badgeAsset->publicSvgUrl(),
            'pngUrl' => $badgeAsset->publicPngUrl(),
            'generatedAt' => $badgeAsset->last_generated_at?->toIso8601String(),
        ]);
    }

    public function destroy(
        Request $request,
        BadgeAsset $badgeAsset,
        DeleteBadgeAsset $delete,
    ): RedirectResponse {
        $this->ensureOwner($request, $badgeAsset);

        $delete($badgeAsset);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.badge_deleted'),
        ]);

        return back();
    }

    private function ensureOwner(Request $request, BadgeAsset $asset): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (int) $asset->user_id === (int) $user->getKey(),
            404,
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
