<?php

namespace App\Support\Colors;

use App\Models\ColorPalette;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Props do Layout Harmonizer na pagina generica (F4, ADR-168).
 *
 * O workspace continua rodando no navegador; o servidor entrega os limites do
 * formulario, a cota conjunta (ADR-170), as tags da conta e a origem opcional
 * vinda do CTA de uma paleta. A seed de origem e a cor base da paleta, nunca o
 * papel `primary`.
 */
final class LayoutSystemToolProps
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?User $user = null, ?Request $request = null): array
    {
        return [
            'authenticated' => $user !== null,
            'limits' => [
                'nameMaxLength' => (int) config('colors.layout_systems.name_max_length', 80),
                'descriptionMaxLength' => (int) config('colors.layout_systems.description_max_length', 500),
                'tagsPerSystem' => (int) config('colors.tags.per_layout_system', 10),
                'tagNameMaxLength' => (int) config('colors.tags.name_max_length', 30),
            ],
            'quota' => $user instanceof User
                ? (new PaletteQuota)->summary($user)
                : null,
            'tags' => array_values(
                $user?->colorTags()
                    ->orderBy('position')
                    ->get(['id', 'name'])
                    ->map(static fn ($tag): array => ['id' => $tag->id, 'name' => $tag->name])
                    ->all() ?? [],
            ),
            'origin' => self::origin($user, $request),
            'currentVersions' => [
                'state' => (int) config('colors.layout_systems.versions.state', 1),
                'schema' => (int) config('colors.layout_systems.versions.schema', 1),
                'snapshot' => (int) config('colors.layout_systems.versions.snapshot', 1),
                'algorithm' => (int) config('colors.layout_systems.versions.algorithm', 1),
                'suggestions' => (int) config('colors.layout_systems.versions.suggestions', 1),
            ],
            // Exportacoes locais (F5): o cliente so tem o idioma ativo, entao
            // as traducoes dos 5 idiomas vem do servidor junto com os limites.
            'exportMessages' => LayoutSystemExportMessages::all(),
            'exportLimits' => self::exportLimits(),
        ];
    }

    /**
     * @return array{prefixMaxLength: int, scopeMaxLength: int}
     */
    public static function exportLimits(): array
    {
        return [
            'prefixMaxLength' => (int) config('colors.layout_systems.export.prefix_max_length', 32),
            'scopeMaxLength' => (int) config('colors.layout_systems.export.scope_max_length', 64),
        ];
    }

    /**
     * Origem do CTA `?palette={ULID}`: so devolve dados de paleta que o
     * visitante pode acessar (dono ou publica ativa). Inacessivel vira `null`.
     *
     * @return array{id: string, name: string, author: string|null, public: bool, seedHex: string, url: string|null}|null
     */
    private static function origin(?User $user, ?Request $request): ?array
    {
        $id = $request?->query('palette');

        if (! is_string($id) || ! Str::isUlid($id)) {
            return null;
        }

        $palette = ColorPalette::query()->find($id);

        if (! $palette instanceof ColorPalette) {
            return null;
        }

        $isOwner = $user instanceof User && (int) $palette->user_id === (int) $user->getKey();
        $isPublic = $palette->isPublic() && ! $palette->isArchived();

        if (! $isOwner && ! $isPublic) {
            return null;
        }

        $author = User::query()->find($palette->user_id);
        $base = $palette->colors['base'] ?? null;

        if (! is_string($base) || $base === '') {
            return null;
        }

        // O parametro de rota usa o slug do idioma (`pt-br`), nao o codigo de
        // traducao do Laravel (`pt_BR`), que nao casa com `{locale}`.
        $routeLocale = request()->route('locale');

        return [
            'id' => $palette->id,
            'name' => $palette->name,
            'author' => $author instanceof User ? $author->name : null,
            'public' => $isPublic,
            'seedHex' => strtolower($base),
            'url' => $isOwner
                ? route('dashboard.colors.palettes.edit', $palette)
                : ($palette->slug !== null
                    ? route('colors.palettes.show', [
                        'locale' => is_string($routeLocale) && $routeLocale !== ''
                            ? $routeLocale
                            : Locales::slug(app()->getLocale()),
                        'slug' => $palette->slug,
                    ])
                    : null),
        ];
    }
}
