<?php

namespace App\Http\Controllers\BadgeForge;

use App\Actions\BadgeForge\CreateBadgeCustomProvider;
use App\Actions\BadgeForge\DeleteBadgeCustomProvider;
use App\Actions\BadgeForge\UpdateBadgeCustomProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\StoreBadgeCustomProviderRequest;
use App\Http\Requests\BadgeForge\UpdateBadgeCustomProviderRequest;
use App\Models\BadgeCustomProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Gestao dos providers customizados (F7, ADR-087).
 *
 * Cadastrar e editar exigem plano pago (`badgeforge.paid`); excluir continua
 * liberado no downgrade. A origem (`url_template`) nunca volta em prop nem em
 * flash. Recurso de outra conta responde 404, como nas demais gestoes.
 */
final class CustomProviderManagementController extends Controller
{
    public function store(
        StoreBadgeCustomProviderRequest $request,
        CreateBadgeCustomProvider $create,
    ): RedirectResponse {
        $create($this->user($request), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.custom_providers.created'),
        ]);

        return back();
    }

    public function update(
        UpdateBadgeCustomProviderRequest $request,
        BadgeCustomProvider $badgeCustomProvider,
        UpdateBadgeCustomProvider $update,
    ): RedirectResponse {
        $this->ensureOwner($request, $badgeCustomProvider);

        $update($badgeCustomProvider, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.custom_providers.updated'),
        ]);

        return back();
    }

    public function destroy(
        Request $request,
        BadgeCustomProvider $badgeCustomProvider,
        DeleteBadgeCustomProvider $delete,
    ): RedirectResponse {
        $this->ensureOwner($request, $badgeCustomProvider);

        $delete($badgeCustomProvider);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.custom_providers.deleted'),
        ]);

        return back();
    }

    private function ensureOwner(Request $request, BadgeCustomProvider $provider): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (int) $provider->user_id === (int) $user->getKey(),
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
