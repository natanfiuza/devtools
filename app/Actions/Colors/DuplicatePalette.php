<?php

namespace App\Actions\Colors;

use App\Enums\PaletteVisibility;
use App\Models\ColorPalette;
use App\Models\User;
use App\Support\Colors\PaletteData;
use App\Support\Colors\PaletteName;
use App\Support\Colors\PaletteQuota;
use App\Support\Colors\PaletteTags;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Duplica uma paleta para outra conta (F4, ADR-124).
 *
 * A copia nasce privada, sem slug e sem OG, guardando a proveniencia. Sem
 * override copia cores, papeis, versao e origem; com override usa o conjunto
 * enviado (duplicar com nova base). A cota e a mesma da criacao, sob o mesmo
 * lock; "uso" so sobe quando quem duplica nao e o dono.
 */
final class DuplicatePalette
{
    public function __construct(
        private readonly PaletteQuota $quota,
        private readonly PaletteTags $tags,
        private readonly PaletteName $name,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, ColorPalette $source, ?PaletteData $override = null): ColorPalette
    {
        return DB::transaction(function () use ($user, $source, $override): ColorPalette {
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if ($this->quota->reached($user)) {
                throw ValidationException::withMessages([
                    'palette' => __('colors.validation.limit_reached'),
                ]);
            }

            $source->loadMissing('tags');
            $data = $override ?? $this->fromSource($source);

            $palette = $user->colorPalettes()->create([
                'duplicated_from_id' => $source->id,
                'name' => $this->name->copy($data->name),
                'description' => $data->description,
                'visibility' => PaletteVisibility::Private,
                'kind' => $data->kind,
                'algorithm_version' => $data->algorithmVersion,
                'colors' => $data->colors,
                'roles' => $data->roles,
                'source' => $data->source,
                'colors_count' => count($data->colors['items']),
                'duplications_count' => 0,
                'slug' => null,
            ]);

            if ($override === null) {
                $this->tags->copyTo($user, $palette, $source->tags);
            } else {
                $this->tags->sync($user, $palette, $data->tags);
            }

            if ($source->user_id !== $user->getKey()) {
                ColorPalette::query()->whereKey($source->getKey())->increment('duplications_count');
            }

            return $palette->load('tags');
        });
    }

    private function fromSource(ColorPalette $source): PaletteData
    {
        return new PaletteData(
            name: $source->name,
            description: $source->description,
            visibility: PaletteVisibility::Private,
            kind: $source->kind,
            algorithmVersion: $source->algorithm_version,
            colors: $source->colors,
            roles: $source->roles,
            source: $source->source,
            tags: array_values(
                $source->tags
                    ->map(static fn ($tag): string => $tag->name)
                    ->all(),
            ),
        );
    }
}
