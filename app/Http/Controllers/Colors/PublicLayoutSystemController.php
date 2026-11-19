<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Models\LayoutSystem;
use App\Models\User;
use App\Support\Colors\LayoutSystemPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina publica de um sistema de layout (F4, ADR-174).
 *
 * So resolve sistema publico, ativo e com slug. Privado, arquivado, aposentado
 * e inexistente caem todos no mesmo 404. A pagina recebe o snapshot completo
 * para exibir o quadro somente leitura; a FK da origem nunca e enviada.
 */
class PublicLayoutSystemController extends Controller
{
    public function show(
        Request $request,
        string $locale,
        string $slug,
        LayoutSystemPresenter $presenter,
    ): Response {
        $system = LayoutSystem::query()
            ->publiclyVisible()
            ->with('tags')
            ->where('slug', $slug)
            ->firstOrFail();

        $user = $request->user();

        return Inertia::render('colors/PublicLayoutSystem', [
            'system' => $presenter->public($system),
            'authenticated' => $user instanceof User,
            'canDuplicate' => ! ($user instanceof User
                && (int) $system->user_id === (int) $user->getKey()),
        ]);
    }
}
