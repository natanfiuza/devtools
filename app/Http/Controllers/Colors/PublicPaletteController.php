<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Models\ColorPalette;
use App\Support\Colors\PaletteOgStorage;
use App\Support\Colors\PalettePresenter;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina publica de uma paleta (F4, ADR-124).
 *
 * So resolve paleta publica, ativa e com slug. Privada, arquivada e slug
 * inexistente caem todos no mesmo 404: a visibilidade nao vaza. O autor
 * nunca aparece -- a pagina e a paleta, nao o perfil.
 */
class PublicPaletteController extends Controller
{
    public function show(
        string $locale,
        string $slug,
        PalettePresenter $presenter,
        PaletteOgStorage $og,
    ): Response {
        $palette = ColorPalette::query()
            ->publiclyVisible()
            ->with('tags')
            ->where('slug', $slug)
            ->firstOrFail();

        $hasOg = $palette->hasOg() && $og->exists($palette->id);

        return Inertia::render('colors/PublicPalette', [
            'palette' => $presenter->public($palette),
            'ogUrl' => $hasOg
                ? URL::route('colors.palettes.og', [
                    'locale' => $locale,
                    'slug' => $palette->slug,
                    'v' => $palette->og_version,
                ])
                : null,
        ]);
    }
}
