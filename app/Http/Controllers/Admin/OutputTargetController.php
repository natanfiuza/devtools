<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminActionType;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\OutputTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alvos de saida do AI SQL Builder (F7).
 *
 * O catalogo nasce na migracao de seed; aqui o admin so liga e desliga um alvo.
 * Slug, idioma, dialetos e plano exigido sao contrato do codigo e do prompt
 * versionado - editar isso pela tela seria mudar a geracao sem deploy nem
 * revisao (ADR-016).
 */
class OutputTargetController extends Controller
{
    use RecordsAdminActions;

    public function index(): Response
    {
        return Inertia::render('admin/OutputTargets', [
            'targets' => OutputTarget::query()
                ->with('requiredPlan')
                ->orderBy('sort')
                ->orderBy('id')
                ->get()
                ->map(fn (OutputTarget $target): array => [
                    'id' => $target->id,
                    'slug' => $target->slug,
                    'label' => $target->label,
                    'language' => $target->language,
                    'dialects' => $target->supported_dialects,
                    'plan' => $target->requiredPlan?->name,
                    'enabled' => $target->enabled,
                    'sort' => $target->sort,
                ])
                ->all(),
        ]);
    }

    public function update(Request $request, OutputTarget $target): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if ($target->enabled === $validated['enabled']) {
            return $this->back('info', __('admin.toasts.output_target_unchanged'));
        }

        $target->enabled = $validated['enabled'];
        $target->save();

        $this->record($request, AdminActionType::OutputTargetUpdated, $target, [
            'slug' => $target->slug,
            'enabled' => $target->enabled,
        ]);

        return $this->back('success', __('admin.toasts.output_target_saved'));
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('admin.output-targets.index');
    }
}
