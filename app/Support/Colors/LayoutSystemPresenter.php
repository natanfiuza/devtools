<?php

namespace App\Support\Colors;

use App\Models\ColorPalette;
use App\Models\ColorTag;
use App\Models\LayoutSystem;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Shapes do sistema de layout para Inertia (F4, ADR-168/ADR-174).
 *
 * A lista e enxuta (previa + metadados); o editor recebe o `state` completo
 * para reabrir sem recalcular; a pagina publica nunca recebe a FK da origem,
 * dados da conta do dono nem credito de paleta privada (ADR-172).
 */
final class LayoutSystemPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function row(LayoutSystem $system, ?int $previewColors = null): array
    {
        $preview = $previewColors ?? (int) config('colors.layout_systems.preview_colors', 6);

        return [
            'id' => $system->id,
            'name' => $system->name,
            'description' => $system->description,
            'visibility' => $system->visibility->value,
            'tokens_count' => $system->tokens_count,
            'duplications_count' => $system->duplications_count,
            'archived' => $system->isArchived(),
            'slug' => $system->slug,
            'preview' => $this->preview($system, $preview),
            'tags' => $this->tags($system),
            'updated_at' => $system->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editor(LayoutSystem $system, ?User $viewer = null): array
    {
        return array_merge($this->row($system), [
            'state' => $system->state,
            'schema_version' => $system->schema_version,
            'algorithm_version' => $system->algorithm_version,
            'suggestions_version' => $system->suggestions_version,
            'snapshot_version' => $system->snapshot_version,
            'archived_at' => $system->archived_at?->toIso8601String(),
            'credit' => $this->editorCredit($system, $viewer),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function public(LayoutSystem $system): array
    {
        return [
            'id' => $system->id,
            'name' => $system->name,
            'description' => $system->description,
            'schema_version' => $system->schema_version,
            'algorithm_version' => $system->algorithm_version,
            'suggestions_version' => $system->suggestions_version,
            'snapshot_version' => $system->snapshot_version,
            'tokens_count' => $system->tokens_count,
            'duplications_count' => $system->duplications_count,
            'slug' => $system->slug,
            'state' => $system->state,
            'tags' => $this->tags($system),
            'author' => $this->author($system),
            'credit' => $this->publicCredit($system),
            'updated_at' => $system->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Byline do autor na pagina publica, no mesmo criterio das paletas (F6).
     *
     * @return array{id: int, name: string, username: string, avatar: string|null}|null
     */
    public function author(LayoutSystem $system): ?array
    {
        $user = User::query()->find($system->user_id);

        if (! $user instanceof User
            || $user->username === null
            || $user->isSuspended()
            || $user->isAnonymized()) {
            return null;
        }

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => ProfilePresenter::avatar($user),
        ];
    }

    /**
     * Credito visivel ao dono: o retrato congelado e a origem acessivel, se
     * ela ainda existir e pertencer a conta (ou continuar publica).
     *
     * @return array{source: array<string, mixed>|null, origin: array<string, mixed>|null}
     */
    private function editorCredit(LayoutSystem $system, ?User $viewer): array
    {
        $source = $system->source;
        $origin = null;

        if ($system->source_palette_id !== null) {
            $palette = ColorPalette::query()->find($system->source_palette_id);

            if ($palette instanceof ColorPalette
                && ($viewer instanceof User
                    ? (int) $palette->user_id === (int) $viewer->getKey() || $palette->isPublic()
                    : $palette->isPublic())) {
                $origin = [
                    'id' => $palette->id,
                    'name' => $palette->name,
                    'slug' => $palette->slug,
                    'public' => $palette->isPublic(),
                ];
            }
        }

        return [
            'source' => is_array($source) ? $source : null,
            'origin' => $origin,
        ];
    }

    /**
     * Credito publico: so fonte que era publica no salvamento. O link some se
     * a paleta deixou de existir ou de ser publica/ativa; o texto permanece.
     *
     * @return array{palette_name: string, palette_slug: string|null, palette_url: string|null}|null
     */
    private function publicCredit(LayoutSystem $system): ?array
    {
        $source = $system->source;

        if (! is_array($source) || ($source['palette_public'] ?? false) !== true) {
            return null;
        }

        $slug = $this->livePaletteSlug($system);

        return [
            'palette_name' => (string) ($source['palette_name'] ?? ''),
            'palette_slug' => $slug,
            'palette_url' => $slug === null
                ? null
                : route('colors.palettes.show', [
                    'locale' => $this->locale(),
                    'slug' => $slug,
                ]),
        ];
    }

    /**
     * O idioma da URL publica e o parametro de rota (`pt-br`), nao o codigo
     * de traducao do Laravel (`pt_BR`); o link de credito precisa abrir a
     * mesma arvore de rotas da pagina atual.
     */
    private function locale(): string
    {
        $locale = request()->route('locale');

        return is_string($locale) && $locale !== '' ? $locale : app()->getLocale();
    }

    private function livePaletteSlug(LayoutSystem $system): ?string
    {
        if (! Schema::hasTable('color_palettes')) {
            return null;
        }

        if ($system->source_palette_id !== null) {
            $slug = ColorPalette::query()
                ->publiclyVisible()
                ->whereKey($system->source_palette_id)
                ->value('slug');

            return is_string($slug) && $slug !== '' ? $slug : null;
        }

        $source = $system->source;

        if (! is_array($source)) {
            return null;
        }

        $slug = $source['palette_slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return ColorPalette::query()->publiclyVisible()->where('slug', $slug)->exists()
            ? $slug
            : null;
    }

    /**
     * Seis cores representativas do snapshot (primeiro tema), sem recalcular.
     *
     * @return list<string>
     */
    private function preview(LayoutSystem $system, int $limit): array
    {
        $wanted = ['primary', 'secondary', 'accent', 'background', 'surface', 'on-background'];
        $themes = $system->state['snapshot']['themes'] ?? null;

        if (! is_array($themes) || ! isset($themes[0]) || ! is_array($themes[0])) {
            return [];
        }

        $tokens = $themes[0]['tokens'] ?? null;

        if (! is_array($tokens)) {
            return [];
        }

        $byId = [];

        foreach ($tokens as $token) {
            if (is_array($token) && is_string($token['id'] ?? null) && is_string($token['hex'] ?? null)) {
                $byId[$token['id']] = $token['hex'];
            }
        }

        $result = [];

        foreach ($wanted as $id) {
            if (isset($byId[$id])) {
                $result[] = $byId[$id];
            }
        }

        return array_slice($result, 0, max(1, $limit));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function tags(LayoutSystem $system): array
    {
        return array_values(
            $system->tags
                ->map(static fn (ColorTag $tag): array => ['id' => $tag->id, 'name' => $tag->name])
                ->all(),
        );
    }
}
