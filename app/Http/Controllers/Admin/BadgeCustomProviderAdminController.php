<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminActionType;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeCustomProviderDefinition;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Providers customizados no painel administrativo (F7, ADR-087).
 *
 * O admin e a unica tela que ve a origem: `url_template` e `pointer` chegam
 * aqui para investigar uma denuncia. Fora daqui, essas colunas nunca saem do
 * servidor. O admin desabilita (a URL publica passa a responder 404) e nunca
 * exclui - o dado e do dono, que pode exportar o CSV e recriar o provider.
 */
final class BadgeCustomProviderAdminController extends Controller
{
    use RecordsAdminActions;

    private const int PER_PAGE = 30;

    public function index(): Response
    {
        $providers = BadgeCustomProvider::query()
            ->with(['user:id,name,email', 'definitions'])
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(static fn (BadgeCustomProvider $provider): array => [
                'id' => $provider->getKey(),
                'name' => $provider->name,
                'ownerName' => $provider->user->name,
                'ownerEmail' => $provider->user->email,
                'urlTemplate' => $provider->url_template,
                'pathParams' => $provider->path_params,
                'examplePath' => $provider->example_path,
                'enabled' => $provider->enabled,
                'createdAt' => Format::date($provider->created_at),
                'definitions' => $provider->definitions
                    ->map(static fn (BadgeCustomProviderDefinition $definition): array => [
                        'id' => $definition->getKey(),
                        'slug' => $definition->slug,
                        'label' => $definition->label,
                        'pointer' => $definition->pointer,
                        'formatter' => $definition->formatter->value,
                    ])
                    ->values()
                    ->all(),
            ]);

        return Inertia::render('admin/BadgeCustomProviders', [
            'providers' => $providers,
        ]);
    }

    public function update(Request $request, BadgeCustomProvider $customProvider): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if ($customProvider->enabled === $validated['enabled']) {
            return $this->back('info', __('admin.toasts.badge_custom_provider_unchanged'));
        }

        $customProvider->enabled = $validated['enabled'];
        $customProvider->save();

        // A auditoria guarda o ULID e o estado, nunca a URL de origem.
        $this->record($request, AdminActionType::CustomProviderUpdated, $customProvider, [
            'id' => $customProvider->getKey(),
            'name' => $customProvider->name,
            'enabled' => $customProvider->enabled,
        ]);

        return $this->back('success', __('admin.toasts.badge_custom_provider_saved'));
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('admin.badgeforge.custom-providers.index');
    }
}
