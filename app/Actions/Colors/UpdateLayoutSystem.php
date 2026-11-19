<?php

namespace App\Actions\Colors;

use App\Enums\PaletteVisibility;
use App\Models\ColorPalette;
use App\Models\LayoutSystem;
use App\Models\User;
use App\Support\Colors\LayoutSystemCredit;
use App\Support\Colors\LayoutSystemData;
use App\Support\Colors\LayoutSystemSlug;
use App\Support\Colors\LayoutSystemState;
use App\Support\Colors\PaletteTags;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Atualiza metadados e estado do sistema (F4, ADR-168).
 *
 * Editar nao consome cota nem trava a linha do usuario. Publicar pela primeira
 * vez gera o slug; tornar privado apenas revoga a URL (o slug fica reservado e
 * volta a valer se o item for publico de novo). Renomear nunca muda o slug.
 */
final class UpdateLayoutSystem
{
    public function __construct(
        private readonly LayoutSystemSlug $slug,
        private readonly PaletteTags $tags,
        private readonly LayoutSystemCredit $credit,
        private readonly LayoutSystemState $state,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(LayoutSystem $system, LayoutSystemData $data): LayoutSystem
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($system, $data): LayoutSystem {
                    $system->loadMissing('tags');

                    $slug = $system->slug;

                    if ($data->visibility === PaletteVisibility::Public && $slug === null) {
                        $slug = $this->slug->unique($this->slug->base($data->name));
                    }

                    $source = $this->source($system->user, $data->sourcePaletteId, $system);

                    $system->forceFill([
                        'name' => $data->name,
                        'description' => $data->description,
                        'visibility' => $data->visibility,
                        'state' => $data->state,
                        'source' => $source['credit'],
                        'source_palette_id' => $source['palette']?->id,
                        'slug' => $slug,
                        ...$this->state->derive($data->state),
                    ])->save();

                    $this->tags->syncTo($system->user, $system, $data->tags);

                    return $system->refresh()->load('tags');
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * O credito congelado so e refeito quando a origem muda; salvar o mesmo
     * sistema de novo nao reescreve o retrato nem perde o autor original.
     *
     * @return array{palette: ColorPalette|null, credit: array<string, mixed>|null}
     *
     * @throws ValidationException
     */
    private function source(User $user, ?string $id, LayoutSystem $system): array
    {
        if ($id === $system->source_palette_id) {
            $source = $system->source;

            return [
                'palette' => $system->sourcePalette,
                'credit' => is_array($source) ? $source : null,
            ];
        }

        if ($id === null || $id === '') {
            return ['palette' => null, 'credit' => null];
        }

        $palette = ColorPalette::query()->find($id);
        $owned = $palette instanceof ColorPalette
            && (int) $palette->user_id === (int) $user->getKey();
        $public = $palette instanceof ColorPalette
            && $palette->isPublic()
            && ! $palette->isArchived();

        if (! $owned && ! $public) {
            throw ValidationException::withMessages([
                'source_palette_id' => __('colors.validation.source_palette_unavailable'),
            ]);
        }

        return ['palette' => $palette, 'credit' => $this->credit->capture($palette)];
    }
}
