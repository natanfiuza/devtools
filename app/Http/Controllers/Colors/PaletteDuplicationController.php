<?php

namespace App\Http\Controllers\Colors;

use App\Actions\Colors\DuplicatePalette;
use App\Enums\PaletteKind;
use App\Enums\PaletteVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\DuplicatePaletteRequest;
use App\Models\ColorPalette;
use App\Models\User;
use App\Support\Colors\PaletteData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Duplicacao de paleta (F4, ADR-124).
 *
 * A copia e sempre privada e respeita a cota do duplicador, sob o mesmo lock
 * da criacao. Do dashboard, o dono copia o retrato gravado; na pagina publica
 * o navegador pode enviar a escala de uma base experimentada, que vira uma
 * paleta `scale` nova -- a origem nao muda.
 */
class PaletteDuplicationController extends Controller
{
    public function store(
        Request $request,
        ColorPalette $colorPalette,
        DuplicatePalette $duplicate,
    ): RedirectResponse {
        $this->ensureOwner($request, $colorPalette);

        $palette = $duplicate($this->user($request), $colorPalette);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.duplicated'),
        ]);

        return redirect()->route('dashboard.colors.palettes.edit', $palette);
    }

    public function storePublic(
        DuplicatePaletteRequest $request,
        string $locale,
        string $slug,
        DuplicatePalette $duplicate,
    ): RedirectResponse {
        $source = ColorPalette::query()
            ->publiclyVisible()
            ->with('tags')
            ->where('slug', $slug)
            ->firstOrFail();

        $override = $request->has('colors') ? $this->override($source, $request) : null;

        $palette = $duplicate($this->user($request), $source, $override);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.duplicated'),
        ]);

        return redirect()->route('dashboard.colors.palettes.edit', $palette);
    }

    private function override(ColorPalette $source, DuplicatePaletteRequest $request): PaletteData
    {
        /** @var array{base: string, items: list<array{hex: string, tone?: int}>} $colors */
        $colors = $request->validated('colors');

        /** @var array{light: array<string, string>, dark: array<string, string>} $roles */
        $roles = $request->validated('roles');

        /** @var list<string> $tags */
        $tags = $request->validated('tags') ?? [];

        return new PaletteData(
            name: $source->name,
            description: $source->description,
            visibility: PaletteVisibility::Private,
            kind: PaletteKind::Scale,
            algorithmVersion: (int) config('colors.algorithm_versions.scale', 1),
            colors: $colors,
            roles: $roles,
            source: null,
            tags: $tags,
        );
    }

    private function ensureOwner(Request $request, ColorPalette $palette): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (int) $palette->user_id === (int) $user->getKey(),
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
