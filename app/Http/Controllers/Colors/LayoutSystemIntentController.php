<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Models\ColorPalette;
use App\Models\LayoutSystem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Intencoes do Layout Harmonizer que sobrevivem ao login (F4).
 *
 * Criar abre o tool com `save=1` e, quando veio do CTA de uma paleta, preserva
 * `palette` (dono ou publica ativa). Duplicar leva o visitante de volta a
 * pagina publica com o fluxo aberto.
 */
class LayoutSystemIntentController extends Controller
{
    public function create(Request $request): RedirectResponse
    {
        $query = ['save' => 1];
        $palette = $request->query('palette');

        if (is_string($palette) && $palette !== '') {
            $this->accessiblePalette($request->user(), $palette);

            $query['palette'] = $palette;
        }

        return redirect()->route('tools.show.grouped', [
            'locale' => app()->getLocale(),
            'group' => 'colors',
            'slug' => 'layout-harmonizer',
            ...$query,
        ]);
    }

    public function duplicate(string $locale, string $slug): RedirectResponse
    {
        LayoutSystem::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        return redirect()->route('colors.systems.show', [
            'locale' => $locale,
            'slug' => $slug,
            'duplicate' => 1,
        ]);
    }

    private function accessiblePalette(?User $user, string $id): ColorPalette
    {
        $palette = ColorPalette::query()->find($id);

        $owned = $palette instanceof ColorPalette
            && $user instanceof User
            && (int) $palette->user_id === (int) $user->getKey();
        $public = $palette instanceof ColorPalette
            && $palette->isPublic()
            && ! $palette->isArchived();

        abort_unless($palette instanceof ColorPalette && ($owned || $public), 404);

        return $palette;
    }
}
