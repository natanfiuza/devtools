<?php

namespace App\Http\Controllers\Colors;

use App\Actions\Colors\ArchiveLayoutSystem;
use App\Actions\Colors\CreateLayoutSystem;
use App\Actions\Colors\DeleteLayoutSystem;
use App\Actions\Colors\RestoreLayoutSystem;
use App\Actions\Colors\UpdateLayoutSystem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\DeleteLayoutSystemRequest;
use App\Http\Requests\Colors\StoreLayoutSystemRequest;
use App\Http\Requests\Colors\UpdateLayoutSystemRequest;
use App\Models\LayoutSystem;
use App\Models\User;
use App\Support\Colors\LayoutSystemData;
use App\Support\Colors\LayoutSystemExportMessages;
use App\Support\Colors\LayoutSystemPresenter;
use App\Support\Colors\LayoutSystemToolProps;
use App\Support\Colors\PaletteQuota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meus sistemas de layout: lista, criacao, edicao e ciclo de vida (F4).
 *
 * Mesma convencao das paletas: busca/filtro/ordenacao no servidor, recurso de
 * outra conta responde 404 e a cota e a conjunta de items da conta (ADR-170).
 */
class LayoutSystemController extends Controller
{
    private const SORTS = ['updated_at', 'created_at', 'name', 'duplications_count'];

    public function index(
        Request $request,
        LayoutSystemPresenter $presenter,
        PaletteQuota $quota,
    ): Response {
        $user = $this->user($request);
        $filters = $this->filters($request);

        $query = LayoutSystem::query()->forUser((int) $user->getKey())->with('tags');

        $this->applyStatus($query, $filters['status']);
        $this->applySearch($query, $filters['q']);
        $this->applyTag($query, $filters['tag']);

        $systems = $query
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate((int) config('colors.layout_systems.per_page', 20))
            ->withQueryString()
            ->through(static fn (LayoutSystem $system): array => $presenter->row($system));

        return Inertia::render('dashboard/colors/LayoutSystemsIndex', [
            'systems' => $systems,
            'filters' => $filters,
            'tags' => $this->accountTags($user),
            'limits' => $quota->summary($user),
            'form' => $this->formLimits(),
        ]);
    }

    public function store(StoreLayoutSystemRequest $request, CreateLayoutSystem $create): RedirectResponse
    {
        $system = $create($this->user($request), LayoutSystemData::fromArray($request->validated()));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.systems.toasts.created'),
        ]);

        return redirect()->route('dashboard.colors.systems.edit', $system);
    }

    public function edit(
        Request $request,
        LayoutSystem $layoutSystem,
        LayoutSystemPresenter $presenter,
        PaletteQuota $quota,
    ): Response {
        $this->ensureOwner($request, $layoutSystem);

        $user = $this->user($request);
        $layoutSystem->load('tags');

        return Inertia::render('dashboard/colors/LayoutSystemEditor', [
            'system' => $presenter->editor($layoutSystem, $user),
            'tags' => $this->accountTags($user),
            'limits' => $quota->summary($user),
            'form' => $this->formLimits(),
            'currentVersions' => $this->currentVersions(),
            // Mesmo pacote da pagina generica: exportar no editor (F5).
            'exportMessages' => LayoutSystemExportMessages::all(),
            'exportLimits' => LayoutSystemToolProps::exportLimits(),
        ]);
    }

    public function update(
        UpdateLayoutSystemRequest $request,
        LayoutSystem $layoutSystem,
        UpdateLayoutSystem $update,
    ): RedirectResponse {
        $this->ensureOwner($request, $layoutSystem);

        if ($layoutSystem->isArchived()) {
            throw ValidationException::withMessages([
                'layout_system' => __('colors.validation.archived'),
            ]);
        }

        $update($layoutSystem, LayoutSystemData::fromArray($request->validated()));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.systems.toasts.updated'),
        ]);

        return back();
    }

    public function archive(
        Request $request,
        LayoutSystem $layoutSystem,
        ArchiveLayoutSystem $archive,
    ): RedirectResponse {
        $this->ensureOwner($request, $layoutSystem);

        $archive($layoutSystem);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.systems.toasts.archived'),
        ]);

        return back();
    }

    public function restore(
        Request $request,
        LayoutSystem $layoutSystem,
        RestoreLayoutSystem $restore,
    ): RedirectResponse {
        $this->ensureOwner($request, $layoutSystem);

        $restore($layoutSystem);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.systems.toasts.restored'),
        ]);

        return back();
    }

    public function destroy(
        DeleteLayoutSystemRequest $request,
        LayoutSystem $layoutSystem,
        DeleteLayoutSystem $delete,
    ): RedirectResponse {
        $this->ensureOwner($request, $layoutSystem);

        $delete($layoutSystem);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.systems.toasts.deleted'),
        ]);

        return redirect()->route('dashboard.colors.systems.index');
    }

    /**
     * @return array{q: string, status: 'active'|'archived', tag: string|null, sort: string, direction: 'asc'|'desc'}
     */
    private function filters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'updated_at');
        $direction = (string) $request->query('direction', 'desc');
        $status = (string) $request->query('status', 'active');
        $tag = $request->query('tag');

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'status' => in_array($status, ['active', 'archived'], true) ? $status : 'active',
            'tag' => is_string($tag) && $tag !== '' ? $tag : null,
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'updated_at',
            'direction' => $direction === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * @param  Builder<LayoutSystem>  $query
     */
    private function applyStatus(Builder $query, string $status): void
    {
        $status === 'archived' ? $query->archived() : $query->active();
    }

    /**
     * @param  Builder<LayoutSystem>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner->whereLike('name', $like)
                ->orWhereLike('description', $like)
                ->orWhereHas('tags', static fn (Builder $tags): Builder => $tags->whereLike('name', $like));
        });
    }

    /**
     * @param  Builder<LayoutSystem>  $query
     */
    private function applyTag(Builder $query, ?string $tag): void
    {
        if ($tag === null) {
            return;
        }

        $query->whereHas('tags', static fn (Builder $tags): Builder => $tags->whereKey($tag));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function accountTags(User $user): array
    {
        return array_values(
            $user->colorTags()
                ->orderBy('position')
                ->get(['id', 'name'])
                ->map(static fn ($tag): array => ['id' => $tag->id, 'name' => $tag->name])
                ->all(),
        );
    }

    /**
     * @return array{nameMaxLength: int, descriptionMaxLength: int, tagsPerSystem: int, tagNameMaxLength: int}
     */
    private function formLimits(): array
    {
        return [
            'nameMaxLength' => (int) config('colors.layout_systems.name_max_length', 80),
            'descriptionMaxLength' => (int) config('colors.layout_systems.description_max_length', 500),
            'tagsPerSystem' => (int) config('colors.tags.per_layout_system', 10),
            'tagNameMaxLength' => (int) config('colors.tags.name_max_length', 30),
        ];
    }

    /**
     * @return array{state: int, schema: int, snapshot: int, algorithm: int, suggestions: int}
     */
    private function currentVersions(): array
    {
        return [
            'state' => (int) config('colors.layout_systems.versions.state', 1),
            'schema' => (int) config('colors.layout_systems.versions.schema', 1),
            'snapshot' => (int) config('colors.layout_systems.versions.snapshot', 1),
            'algorithm' => (int) config('colors.layout_systems.versions.algorithm', 1),
            'suggestions' => (int) config('colors.layout_systems.versions.suggestions', 1),
        ];
    }

    private function ensureOwner(Request $request, LayoutSystem $system): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (int) $system->user_id === (int) $user->getKey(),
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
