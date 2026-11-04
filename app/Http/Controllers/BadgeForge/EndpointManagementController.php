<?php

namespace App\Http\Controllers\BadgeForge;

use App\Actions\BadgeForge\CreateBadgeEndpoint;
use App\Actions\BadgeForge\DeleteBadgeEndpoint;
use App\Actions\BadgeForge\UpdateBadgeEndpoint;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\StoreBadgeEndpointRequest;
use App\Http\Requests\BadgeForge\UpdateBadgeEndpointRequest;
use App\Models\BadgeEndpoint;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Gestao dos endpoints JSON (F5, ADR-083).
 *
 * A imagem publica (`/api/badges/endpoint/{ulid}`) e stateless e nao passa por
 * aqui. Estas rotas sao as acoes do dono; recurso de outra conta responde 404
 * e nunca revela o ULID. Free pode cadastrar ("apenas cadastrados"): a imagem
 * continua anonima, o limite e o rate limit.
 */
final class EndpointManagementController extends Controller
{
    public function store(
        StoreBadgeEndpointRequest $request,
        CreateBadgeEndpoint $create,
    ): RedirectResponse {
        $create($this->user($request), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.endpoint.created'),
        ]);

        return back();
    }

    public function update(
        UpdateBadgeEndpointRequest $request,
        BadgeEndpoint $badgeEndpoint,
        UpdateBadgeEndpoint $update,
    ): RedirectResponse {
        $this->ensureOwner($request, $badgeEndpoint);

        $update($badgeEndpoint, (string) $request->validated('name'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.endpoint.updated'),
        ]);

        return back();
    }

    public function destroy(
        Request $request,
        BadgeEndpoint $badgeEndpoint,
        DeleteBadgeEndpoint $delete,
    ): RedirectResponse {
        $this->ensureOwner($request, $badgeEndpoint);

        $delete($badgeEndpoint);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.endpoint.deleted'),
        ]);

        return back();
    }

    private function ensureOwner(Request $request, BadgeEndpoint $endpoint): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (int) $endpoint->user_id === (int) $user->getKey(),
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
