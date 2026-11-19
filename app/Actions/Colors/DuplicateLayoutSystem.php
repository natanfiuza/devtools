<?php

namespace App\Actions\Colors;

use App\Enums\PaletteVisibility;
use App\Models\LayoutSystem;
use App\Models\User;
use App\Support\Colors\PaletteQuota;
use App\Support\Colors\PaletteTags;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Duplica um sistema de layout (F4, ADR-165).
 *
 * A copia nasce privada, sem slug, com o retrato e as versoes da origem. O
 * credito congelado e copiado, mas a FK da paleta so sobrevive quando a copia
 * fica na mesma conta; entre contas a linhagem vive em `duplicated_from_id`.
 * A cota e a da criacao, sob o mesmo lock.
 */
final class DuplicateLayoutSystem
{
    public function __construct(
        private readonly PaletteQuota $quota,
        private readonly PaletteTags $tags,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, LayoutSystem $source): LayoutSystem
    {
        return DB::transaction(function () use ($user, $source): LayoutSystem {
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if ($this->quota->reached($user)) {
                throw ValidationException::withMessages([
                    'layout_system' => __('colors.validation.limit_reached'),
                ]);
            }

            $source->loadMissing('tags');
            $sameOwner = (int) $source->user_id === (int) $user->getKey();

            $copy = $user->layoutSystems()->create([
                'duplicated_from_id' => $source->id,
                'source_palette_id' => $sameOwner ? $source->source_palette_id : null,
                'name' => $this->copyName($source->name),
                'description' => $source->description,
                'visibility' => PaletteVisibility::Private,
                'state' => $source->state,
                'source' => $source->source,
                'schema_version' => $source->schema_version,
                'algorithm_version' => $source->algorithm_version,
                'suggestions_version' => $source->suggestions_version,
                'snapshot_version' => $source->snapshot_version,
                'tokens_count' => $source->tokens_count,
                'duplications_count' => 0,
                'slug' => null,
            ]);

            $this->tags->copyTo($user, $copy, $source->tags);

            // O contador e do autor original: a copia do dono nao infla.
            if (! $sameOwner) {
                LayoutSystem::query()->whereKey($source->getKey())->increment('duplications_count');
            }

            return $copy->load('tags');
        });
    }

    private function copyName(string $name): string
    {
        $suffix = (string) __('colors.palette.copy_suffix');
        $max = max(1, (int) config('colors.layout_systems.name_max_length', 80));
        $room = max(1, $max - strlen($suffix));

        return rtrim(mb_substr($name, 0, $room)).$suffix;
    }
}
