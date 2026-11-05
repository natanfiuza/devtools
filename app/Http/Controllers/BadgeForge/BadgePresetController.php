<?php

namespace App\Http\Controllers\BadgeForge;

use App\Actions\BadgeForge\StoreBadgePreset;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\StoreBadgePresetRequest;
use App\Http\Requests\BadgeForge\UpdateBadgePresetRequest;
use App\Models\BadgePreset;
use App\Models\User;
use App\Support\BadgeForge\PresetSpecNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Presets da conta (ADR-079).
 *
 * O preset e um snapshot normalizado do formulario, nao um badge publicado.
 * O formulario passa pelo mesmo normalizador da URL para que aplicar o preset
 * reproduza exatamente o badge que seria gerado a mao. Aplicar e tarefa do
 * cliente: o servidor so cria, renomeia e exclui.
 */
class BadgePresetController extends Controller
{
    public function store(
        StoreBadgePresetRequest $request,
        PresetSpecNormalizer $normalizer,
        StoreBadgePreset $store,
    ): RedirectResponse {
        $user = $this->user($request);

        /** @var array<string, mixed> $form */
        $form = (array) $request->validated('form');

        $store(
            $user,
            (string) $request->validated('name'),
            $normalizer->normalize($form, $user),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.preset_created'),
        ]);

        return back();
    }

    public function update(
        UpdateBadgePresetRequest $request,
        BadgePreset $badgePreset,
    ): RedirectResponse {
        $this->ensureOwner($request, $badgePreset);

        $badgePreset->forceFill([
            'name' => (string) $request->validated('name'),
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.preset_updated'),
        ]);

        return back();
    }

    public function destroy(Request $request, BadgePreset $badgePreset): RedirectResponse
    {
        $this->ensureOwner($request, $badgePreset);

        $badgePreset->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.preset_deleted'),
        ]);

        return back();
    }

    private function ensureOwner(Request $request, BadgePreset $preset): void
    {
        $user = $request->user();

        // Recurso de outra conta responde 404, nunca 403.
        abort_unless(
            $user instanceof User && (int) $preset->user_id === (int) $user->getKey(),
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
