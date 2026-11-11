<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\UploadPaletteOgRequest;
use App\Models\ColorPalette;
use App\Models\User;
use App\Support\Colors\PaletteOgStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Imagem OG dinamica de paleta (F4, ADR-125).
 *
 * O desenho acontece no navegador; aqui so entram o upload ja validado, o
 * serving publico condicionado a paleta publica ativa e a remocao. A URL
 * carrega `?v={og_version}` para vencer o cache quando a imagem muda.
 */
class PaletteOgController extends Controller
{
    public function show(
        string $locale,
        string $slug,
        PaletteOgStorage $og,
    ): BinaryFileResponse {
        $palette = ColorPalette::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        abort_unless($palette->hasOg() && $og->exists($palette->id), 404);

        $seconds = max(60, (int) config('colors.og.cache_seconds', 86400));

        return response()->file($og->path($palette->id), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age='.$seconds,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(
        UploadPaletteOgRequest $request,
        ColorPalette $colorPalette,
        PaletteOgStorage $og,
    ): RedirectResponse {
        $this->ensureOwner($request, $colorPalette);

        $og->put($colorPalette->id, (string) $request->file('image')?->get());

        $colorPalette->forceFill([
            'og_version' => (int) $colorPalette->og_version + 1,
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.og_saved'),
        ]);

        return back();
    }

    public function destroy(
        Request $request,
        ColorPalette $colorPalette,
        PaletteOgStorage $og,
    ): RedirectResponse {
        $this->ensureOwner($request, $colorPalette);

        $og->delete($colorPalette->id);
        $colorPalette->forceFill(['og_version' => null])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.og_removed'),
        ]);

        return back();
    }

    private function ensureOwner(Request $request, ColorPalette $palette): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (int) $palette->user_id === (int) $user->getKey(),
            404,
        );
    }
}
