<?php

namespace App\Support\Colors;

use App\Models\ColorPalette;
use App\Models\ColorTag;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tags de paleta por usuario (F4, ADR-103).
 *
 * A tag e uma entidade do dono: renomear alcanca todas as paletas e a ordem
 * (`position`) e global na conta. `key` normaliza caixa e espacos para
 * impedir duplicatas; o limite por paleta vem de `config/colors.php`.
 */
final class PaletteTags
{
    public function normalize(string $name): string
    {
        return Str::lower(Str::squish($name));
    }

    /**
     * Sincroniza as tags da paleta a partir dos nomes enviados, criando as
     * que ainda nao existem para o usuario e preservando a ordem de envio.
     *
     * @param  list<string>  $names
     */
    public function sync(User $user, ColorPalette $palette, array $names): void
    {
        $ids = [];

        foreach ($this->dedupe($names) as $name) {
            $ids[] = $this->findOrCreate($user, $name)->getKey();
        }

        $palette->tags()->sync($ids);
    }

    /**
     * Copia as tags de uma paleta de origem para o duplicador, criando na
     * conta dele as que ainda nao existem.
     *
     * @param  iterable<int, ColorTag>  $sourceTags
     */
    public function copyTo(User $user, ColorPalette $palette, iterable $sourceTags): void
    {
        $names = [];

        foreach ($sourceTags as $tag) {
            $names[] = $tag->name;
        }

        $this->sync($user, $palette, $names);
    }

    public function create(User $user, string $name): ColorTag
    {
        $key = $this->normalize($name);

        if ($user->colorTags()->where('key', $key)->exists()) {
            throw ValidationException::withMessages([
                'name' => __('colors.validation.tag_exists'),
            ]);
        }

        return $this->findOrCreate($user, $name);
    }

    public function rename(ColorTag $tag, string $name): void
    {
        $key = $this->normalize($name);

        $collision = ColorTag::query()
            ->where('user_id', $tag->user_id)
            ->where('key', $key)
            ->whereKeyNot($tag->getKey())
            ->exists();

        if ($collision) {
            throw ValidationException::withMessages([
                'name' => __('colors.validation.tag_exists'),
            ]);
        }

        $tag->forceFill(['name' => Str::squish($name), 'key' => $key])->save();
    }

    /**
     * @param  list<string>  $ids
     */
    public function reorder(User $user, array $ids): void
    {
        $owned = $user->colorTags()->pluck('id')->all();

        foreach ($ids as $id) {
            if (! in_array($id, $owned, true)) {
                abort(404);
            }
        }

        foreach ($ids as $position => $id) {
            ColorTag::query()->whereKey($id)->update(['position' => $position]);
        }
    }

    public function delete(ColorTag $tag): void
    {
        $tag->palettes()->detach();
        $tag->delete();
    }

    private function findOrCreate(User $user, string $name): ColorTag
    {
        $key = $this->normalize($name);

        $existing = $user->colorTags()->where('key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        $position = (int) $user->colorTags()->max('position') + 1;

        return $user->colorTags()->create([
            'name' => Str::squish($name),
            'key' => $key,
            'position' => $position,
        ]);
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function dedupe(array $names): array
    {
        $seen = [];
        $result = [];

        foreach ($names as $name) {
            $trimmed = Str::squish(trim($name));

            if ($trimmed === '') {
                continue;
            }

            $key = $this->normalize($trimmed);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $trimmed;
        }

        return $result;
    }
}
