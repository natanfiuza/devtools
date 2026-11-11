<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Models\ColorPalette;
use Illuminate\Http\RedirectResponse;

/**
 * Intencoes que sobrevivem ao login (F4).
 *
 * Criar e duplicar usam o proprio gerador/pagina publica: estas rotas so
 * levam o visitante de volta ao lugar certo com o fluxo aberto. A intencao e
 * query string, nunca uma tela intermediaria.
 */
class PaletteIntentController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect()->route('tools.show.grouped', [
            'locale' => app()->getLocale(),
            'group' => 'colors',
            'slug' => 'palette-generator',
            'save' => 1,
        ]);
    }

    public function duplicate(string $locale, string $slug): RedirectResponse
    {
        ColorPalette::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        return redirect()->route('colors.palettes.show', [
            'locale' => $locale,
            'slug' => $slug,
            'duplicate' => 1,
        ]);
    }
}
