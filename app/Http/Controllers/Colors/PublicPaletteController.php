<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Models\ColorPalette;
use App\Models\ColorPaletteFavorite;
use App\Models\User;
use App\Support\Colors\PaletteOgStorage;
use App\Support\Colors\PalettePresenter;
use Illuminate\Http\Request;
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
        Request $request,
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
        $user = $request->user();

        // A estrela so aparece para quem pode favoritar: conta logada e
        // paleta de outra pessoa (F5, ADR-128). O dono ja a tem na lista.
        $canFavorite = $user instanceof User
            && (int) $palette->user_id !== (int) $user->getKey();

        $favorited = $canFavorite
            && ColorPaletteFavorite::query()
                ->forUser((int) $user->getKey())
                ->where('color_palette_id', $palette->id)
                ->exists();

        return Inertia::render('colors/PublicPalette', [
            'palette' => $presenter->public($palette),
            'favorited' => $favorited,
            'canFavorite' => $canFavorite,
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
