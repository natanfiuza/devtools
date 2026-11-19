<?php

namespace App\Http\Controllers\Colors;

use App\Actions\Colors\DuplicateLayoutSystem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\DuplicateLayoutSystemRequest;
use App\Models\LayoutSystem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Duplicacao de sistema de layout (F4, ADR-165).
 *
 * A copia e sempre privada, respeita a cota conjunta sob o mesmo lock da
 * criacao e abre o editor da copia. Do dashboard, o dono copia o proprio
 * sistema; na pagina publica, qualquer conta logada copia o sistema publico.
 */
class LayoutSystemDuplicationController extends Controller
{
    public function store(
        Request $request,
        LayoutSystem $layoutSystem,
        DuplicateLayoutSystem $duplicate,
    ): RedirectResponse {
        $this->ensureOwner($request, $layoutSystem);

        return $this->duplicate($request, $layoutSystem, $duplicate);
    }

    public function storePublic(
        DuplicateLayoutSystemRequest $request,
        string $locale,
        string $slug,
        DuplicateLayoutSystem $duplicate,
    ): RedirectResponse {
        $source = LayoutSystem::query()
            ->publiclyVisible()
            ->with('tags')
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->duplicate($request, $source, $duplicate);
    }

    private function duplicate(
        Request $request,
        LayoutSystem $source,
        DuplicateLayoutSystem $duplicate,
    ): RedirectResponse {
        $copy = $duplicate($this->user($request), $source);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.systems.toasts.duplicated'),
        ]);

        return redirect()->route('dashboard.colors.systems.edit', $copy);
    }

    private function ensureOwner(Request $request, LayoutSystem $system): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (int) $system->user_id === (int) $user->getKey(),
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
