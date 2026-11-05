<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminActionType;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalogo global de providers do BadgeForge (F7, ADR-086).
 *
 * O catalogo nasce na migracao de seed; aqui o admin so liga e desliga um
 * provider ou uma definicao. Desligar tira a rota publica e a documentacao
 * sem apagar historico. Slug, URL e parser continuam contrato do codigo -
 * editar isso pela tela seria mudar a extracao sem deploy nem revisao.
 */
final class BadgeProviderAdminController extends Controller
{
    use RecordsAdminActions;

    public function index(): Response
    {
        $providers = BadgeProvider::query()
            ->with(['definitions' => static fn ($query) => $query->orderBy('sort')->orderBy('id')])
            ->orderBy('category')
            ->orderBy('slug')
            ->get()
            ->map(static fn (BadgeProvider $provider): array => [
                'id' => $provider->id,
                'slug' => $provider->slug,
                'name' => $provider->name,
                'category' => $provider->category,
                'enabled' => $provider->enabled,
                'definitions' => $provider->definitions
                    ->map(static fn (BadgeProviderDefinition $definition): array => [
                        'id' => $definition->id,
                        'slug' => $definition->slug,
                        'name' => $definition->name,
                        'label' => $definition->label,
                        'enabled' => $definition->enabled,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();

        return Inertia::render('admin/BadgeProviders', [
            'providers' => $providers,
        ]);
    }

    public function update(Request $request, BadgeProvider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if ($provider->enabled === $validated['enabled']) {
            return $this->back('info', __('admin.toasts.badge_provider_unchanged'));
        }

        $provider->enabled = $validated['enabled'];
        $provider->save();

        $this->record($request, AdminActionType::ProviderUpdated, $provider, [
            'slug' => $provider->slug,
            'enabled' => $provider->enabled,
        ]);

        return $this->back('success', __('admin.toasts.badge_provider_saved'));
    }

    public function updateDefinition(
        Request $request,
        BadgeProvider $provider,
        BadgeProviderDefinition $definition,
    ): RedirectResponse {
        abort_unless($definition->provider_id === $provider->getKey(), 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if ($definition->enabled === $validated['enabled']) {
            return $this->back('info', __('admin.toasts.badge_provider_unchanged'));
        }

        $definition->enabled = $validated['enabled'];
        $definition->save();

        $this->record($request, AdminActionType::ProviderDefinitionUpdated, $provider, [
            'slug' => $provider->slug,
            'definition' => $definition->slug,
            'enabled' => $definition->enabled,
        ]);

        return $this->back('success', __('admin.toasts.badge_provider_saved'));
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('admin.badgeforge.providers.index');
    }
}
