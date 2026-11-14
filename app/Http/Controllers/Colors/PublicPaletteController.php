<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Models\ColorPalette;
use App\Models\ColorPaletteFavorite;
use App\Models\ColorPaletteLike;
use App\Models\User;
use App\Models\UserFollow;
use App\Support\Colors\AnonymousIdentity;
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
 * inexistente caem todos no mesmo 404: a visibilidade nao vaza. Desde a F6 a
 * pagina mostra o autor visivel (byline), a curtida e o seguir; a estrela da
 * F5 continua independente da curtida (ADR-128/ADR-135).
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
            ->with(['tags', 'user'])
            ->where('slug', $slug)
            ->firstOrFail();

        $hasOg = $palette->hasOg() && $og->exists($palette->id);
        $user = $request->user();
        $viewerId = $user instanceof User ? (int) $user->getKey() : null;
        $isOwner = $viewerId !== null && $viewerId === (int) $palette->user_id;

        // A estrela so aparece para quem pode favoritar: conta logada e
        // paleta de outra pessoa (F5, ADR-128). O dono ja a tem na lista.
        $canFavorite = $viewerId !== null && ! $isOwner;

        $favorited = $canFavorite
            && ColorPaletteFavorite::query()
                ->forUser((int) $viewerId)
                ->where('color_palette_id', $palette->id)
                ->exists();

        // O autor so entra na byline quando continua visivel; sem ele nao ha
        // o que seguir (F6, ADR-134).
        $author = $presenter->author($palette);
        $canFollow = $author !== null && $viewerId !== null && ! $isOwner;

        $following = $canFollow && UserFollow::query()
            ->where('follower_id', (int) $viewerId)
            ->where('followed_id', $palette->user_id)
            ->active()
            ->exists();

        return Inertia::render('colors/PublicPalette', [
            'palette' => $presenter->public($palette),
            'favorited' => $favorited,
            'canFavorite' => $canFavorite,
            'liked' => $this->liked($request, $palette, $user instanceof User ? $user : null),
            // O dono nao curte a propria paleta; o visitante curte direto.
            'canLike' => ! $isOwner,
            'following' => $following,
            'canFollow' => $canFollow,
            'ogUrl' => $hasOg
                ? URL::route('colors.palettes.og', [
                    'locale' => $locale,
                    'slug' => $palette->slug,
                    'v' => $palette->og_version,
                ])
                : null,
        ]);
    }

    /**
     * A conta curte pelo id; o visitante, pelo identificador do cookie.
     */
    private function liked(Request $request, ColorPalette $palette, ?User $user): bool
    {
        $query = ColorPaletteLike::query()
            ->where('color_palette_id', $palette->id)
            ->active();

        if ($user instanceof User) {
            return $query->where('user_id', $user->getKey())->exists();
        }

        $identifier = AnonymousIdentity::read($request);

        return $identifier !== null
            && $query->where('anonymous_id', $identifier)->exists();
    }
}
