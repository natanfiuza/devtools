<?php

namespace App\Http\Controllers\Colors;

use App\Actions\Colors\ArchivePalette;
use App\Actions\Colors\CreatePalette;
use App\Actions\Colors\DeletePalette;
use App\Actions\Colors\RestorePalette;
use App\Actions\Colors\UpdatePalette;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\DeletePaletteRequest;
use App\Http\Requests\Colors\StorePaletteRequest;
use App\Http\Requests\Colors\UpdatePaletteRequest;
use App\Models\ColorPalette;
use App\Models\ColorPaletteFavorite;
use App\Models\User;
use App\Support\Colors\ColorsToolProps;
use App\Support\Colors\PaletteData;
use App\Support\Colors\PalettePresenter;
use App\Support\Colors\PaletteQuota;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Minhas Paletas: lista, criacao, edicao, arquivamento e exclusao (F4).
 *
 * Busca, filtro e ordenacao rodam no servidor (ADR-109): nada e filtrado no
 * cliente, entao a paginacao continua correta em qualquer tamanho de acervo.
 * Recurso de outra conta responde 404, nunca 403 -- mesmo padrao do resto da
 * area autenticada, para nao revelar que o ULID existe.
 */
class PaletteController extends Controller
{
    private const SORTS = ['updated_at', 'created_at', 'name', 'colors_count', 'duplications_count'];

    public function index(
        Request $request,
        PalettePresenter $presenter,
        PaletteQuota $quota,
    ): Response {
        $user = $this->user($request);
        $filters = $this->filters($request);
        $tab = $request->query('tab') === 'favorites' ? 'favorites' : 'mine';

        $query = ColorPalette::query()->forUser((int) $user->getKey())->with('tags');

        $this->applyStatus($query, $filters['status']);
        $this->applySearch($query, $filters['q']);
        $this->applyTag($query, $filters['tag']);

        $palettes = $query
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate((int) config('colors.palettes.per_page', 20))
            ->withQueryString()
            ->through(static fn (ColorPalette $palette): array => $presenter->row($palette));

        return Inertia::render('dashboard/colors/PalettesIndex', [
            'tab' => $tab,
            'palettes' => $palettes,
            'favorites' => $tab === 'favorites'
                ? $this->favorites($user, $presenter)
                : null,
            'filters' => $filters,
            'tags' => $user->colorTags()
                ->orderBy('position')
                ->get(['id', 'name'])
                ->map(static fn ($tag): array => ['id' => $tag->id, 'name' => $tag->name])
                ->values()
                ->all(),
            'limits' => $quota->summary($user),
            'form' => ColorsToolProps::formLimits(),
        ]);
    }

    public function store(StorePaletteRequest $request, CreatePalette $create): RedirectResponse
    {
        $palette = $create($this->user($request), PaletteData::fromArray($request->validated()));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.created'),
        ]);

        return redirect()->route('dashboard.colors.palettes.edit', $palette);
    }

    public function edit(
        Request $request,
        ColorPalette $colorPalette,
        PalettePresenter $presenter,
        PaletteQuota $quota,
    ): Response {
        $this->ensureOwner($request, $colorPalette);

        $user = $this->user($request);
        $colorPalette->load('tags');

        return Inertia::render('dashboard/colors/PaletteEditor', [
            'palette' => $presenter->editor($colorPalette),
            'tags' => $user->colorTags()
                ->orderBy('position')
                ->get(['id', 'name'])
                ->map(static fn ($tag): array => ['id' => $tag->id, 'name' => $tag->name])
                ->values()
                ->all(),
            'limits' => $quota->summary($user),
            // O editor reusa o workspace do gerador, que tambem importa imagem.
            'form' => ColorsToolProps::formLimits(),
            'image' => ColorsToolProps::imageLimits(),
            // O editor tambem abre a biblioteca compartilhada de cores (F5).
            'library' => ColorsToolProps::library($user),
        ]);
    }

    public function update(
        UpdatePaletteRequest $request,
        ColorPalette $colorPalette,
        UpdatePalette $update,
    ): RedirectResponse {
        $this->ensureOwner($request, $colorPalette);

        if ($colorPalette->isArchived()) {
            throw ValidationException::withMessages([
                'palette' => __('colors.validation.archived'),
            ]);
        }

        $update($colorPalette, PaletteData::fromArray($request->validated()));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.updated'),
        ]);

        return back();
    }

    public function archive(
        Request $request,
        ColorPalette $colorPalette,
        ArchivePalette $archive,
    ): RedirectResponse {
        $this->ensureOwner($request, $colorPalette);

        $archive($colorPalette);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.archived'),
        ]);

        return back();
    }

    public function restore(
        Request $request,
        ColorPalette $colorPalette,
        RestorePalette $restore,
    ): RedirectResponse {
        $this->ensureOwner($request, $colorPalette);

        $restore($colorPalette);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.restored'),
        ]);

        return back();
    }

    public function destroy(
        DeletePaletteRequest $request,
        ColorPalette $colorPalette,
        DeletePalette $delete,
    ): RedirectResponse {
        $this->ensureOwner($request, $colorPalette);

        $delete($colorPalette);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.deleted'),
        ]);

        return redirect()->route('dashboard.colors.palettes.index');
    }

    /**
     * Favoritas: so paletas que continuam publicas e ativas (F5, ADR-128).
     *
     * O vinculo guarda apenas a referencia; o retrato vem da origem. Paleta
     * que virou privada, foi arquivada ou excluida some da lista sem apagar o
     * favorito - se voltar a ser publica, ela reaparece.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function favorites(User $user, PalettePresenter $presenter): LengthAwarePaginator
    {
        return ColorPaletteFavorite::query()
            ->forUser((int) $user->getKey())
            ->whereHas('palette', static fn (Builder $palette): Builder => $palette->publiclyVisible())
            ->with('palette.tags')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) config('colors.palettes.per_page', 20))
            ->withQueryString()
            ->through(static fn (ColorPaletteFavorite $favorite): array => $presenter->favoriteRow($favorite));
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
     * @param  Builder<ColorPalette>  $query
     */
    private function applyStatus(Builder $query, string $status): void
    {
        $status === 'archived' ? $query->archived() : $query->active();
    }

    /**
     * Caixa e coberta pelo collation do banco; acentos nao sao normalizados
     * (comportamento aceito na ADR-109).
     *
     * @param  Builder<ColorPalette>  $query
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
     * @param  Builder<ColorPalette>  $query
     */
    private function applyTag(Builder $query, ?string $tag): void
    {
        if ($tag === null) {
            return;
        }

        $query->whereHas('tags', static fn (Builder $tags): Builder => $tags->whereKey($tag));
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
