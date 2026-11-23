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
use App\Support\Colors\PaletteQuota;
use App\Support\Colors\PaletteTags;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria um sistema de layout para a conta (F4, ADR-168/ADR-170).
 *
 * Palpite igual ao da paleta: a linha do usuario e travada antes da contagem
 * conjunta, o slug so nasce se o sistema ja nasce publico e a colisao de slug
 * repete a transacao. As versoes e `tokens_count` sao derivadas do snapshot
 * validado; o credito da origem e congelado aqui, nunca vem do cliente.
 */
final class CreateLayoutSystem
{
    public function __construct(
        private readonly PaletteQuota $quota,
        private readonly LayoutSystemSlug $slug,
        private readonly PaletteTags $tags,
        private readonly LayoutSystemCredit $credit,
        private readonly LayoutSystemState $state,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, LayoutSystemData $data): LayoutSystem
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $data): LayoutSystem {
                    User::query()->whereKey($user->getKey())->lockForUpdate()->first();

                    if ($this->quota->reached($user)) {
                        throw ValidationException::withMessages([
                            'layout_system' => __('colors.systems.validation.limit_reached'),
                        ]);
                    }

                    $source = $this->source($user, $data->sourcePaletteId);
                    $versions = $this->state->derive($data->state);

                    $slug = $data->visibility === PaletteVisibility::Public
                        ? $this->slug->unique($this->slug->base($data->name))
                        : null;

                    $system = $user->layoutSystems()->create([
                        'name' => $data->name,
                        'description' => $data->description,
                        'visibility' => $data->visibility,
                        'state' => $data->state,
                        'source' => $this->credit->capture($source),
                        'source_palette_id' => $source?->id,
                        'duplications_count' => 0,
                        'slug' => $slug,
                        ...$versions,
                    ]);

                    $this->tags->syncTo($user, $system, $data->tags);

                    return $system->load('tags');
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * A origem so pode ser uma paleta do proprio usuario ou uma publica ativa;
     * inacessivel falha como campo do formulario, sem creditar nada.
     *
     * @throws ValidationException
     */
    private function source(User $user, ?string $id): ?ColorPalette
    {
        if ($id === null || $id === '') {
            return null;
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

        return $palette;
    }
}
