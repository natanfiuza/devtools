<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminActionType;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * "Entrar como" (ADR-025).
 *
 * O ator da sessao passa a ser o alvo; o que identifica de onde se veio e a
 * chave em App\Support\Impersonation. Sair vive fora do grupo do painel, de
 * proposito: enquanto se esta como outro, o painel fica inacessivel - o alvo
 * nao e admin - e a unica porta de volta e esta rota.
 */
class ImpersonationController extends Controller
{
    use RecordsAdminActions;

    /**
     * Entra na conta do alvo.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        // A policy ja recusa a propria conta, a conta de outro admin e a conta
        // suspensa (UserPolicy::impersonate).
        Gate::authorize('impersonate', $user);

        // Encadear apagaria o caminho de volta: quem ja esta como outro nao
        // abre uma segunda impersonation.
        abort_if(Impersonation::isActive($request), 403);

        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        // A auditoria vem primeiro: `record` le o usuario autenticado, e a
        // partir da proxima linha ele passa a ser o alvo.
        $this->record($request, AdminActionType::UserImpersonated, $user);

        Impersonation::start($request, $actor, $user);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('admin.toasts.impersonation_started', ['name' => $user->name]),
        ]);

        return to_route('dashboard');
    }

    /**
     * Volta para a propria conta.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $actor = Impersonation::actor($request);

        // Sem impersonation em curso nao ha o que desfazer: quem chegou aqui
        // por engano volta para a propria area.
        if ($actor === null) {
            return to_route('dashboard');
        }

        Impersonation::stop($request, $actor);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('admin.toasts.impersonation_stopped'),
        ]);

        return to_route('admin.users.index');
    }
}
