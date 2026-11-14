<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Models\ColorPalette;
use App\Models\User;
use App\Models\UserFollow;
use App\Support\Colors\ProfilePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perfil publico por username (F6, ADR-134).
 *
 * Conta inexistente, suspensa ou anonimizada cai no mesmo 404. O perfil mostra
 * apenas paletas publicas e ativas, com a contagem de curtidas ativas, e nao
 * lista seguidores nem seguindo - a F6 nao cria essas superficies. O visitante
 * pode ler; seguir exige conta.
 */
class PublicProfileController extends Controller
{
    public function show(
        Request $request,
        string $locale,
        string $username,
        ProfilePresenter $presenter,
    ): Response {
        $profile = User::query()->where('username', $username)->first();

        abort_unless(
            $profile instanceof User && ! $profile->isSuspended() && ! $profile->isAnonymized(),
            404,
        );

        return Inertia::render('colors/PublicProfile', [
            'profile' => $presenter->profile($profile),
            'palettes' => $this->palettes($profile, $presenter),
            ...$this->viewer($request, $profile),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function palettes(User $profile, ProfilePresenter $presenter): LengthAwarePaginator
    {
        return ColorPalette::query()
            ->forUser((int) $profile->getKey())
            ->publiclyVisible()
            ->with('tags')
            ->withCount([
                // O escopo `active` nao e visivel ao PHPStan dentro deste
                // closure; a condicao e a mesma (`liked_at` nao nulo).
                'likes as likes_count' => static fn (Builder $query): Builder => $query->whereNotNull('liked_at'),
            ])
            ->orderByDesc('updated_at')
            ->paginate(max(1, (int) config('colors.social.profile_per_page', 12)))
            ->withQueryString()
            ->through(static fn (ColorPalette $palette): array => $presenter->paletteRow($palette));
    }

    /**
     * @return array{following: bool, canFollow: bool, isOwnProfile: bool}
     */
    private function viewer(Request $request, User $profile): array
    {
        $viewer = $request->user();
        $viewerId = $viewer instanceof User ? (int) $viewer->getKey() : null;
        $isOwnProfile = $viewerId !== null && $viewerId === (int) $profile->getKey();
        $canFollow = $viewerId !== null && ! $isOwnProfile;

        $following = $canFollow && UserFollow::query()
            ->where('follower_id', $viewerId)
            ->where('followed_id', $profile->getKey())
            ->active()
            ->exists();

        return [
            'following' => $following,
            'canFollow' => $canFollow,
            'isOwnProfile' => $isOwnProfile,
        ];
    }
}
