<?php

namespace App\Actions\Colors;

use App\Enums\PaletteVisibility;
use App\Models\ColorPalette;
use App\Models\User;
use App\Support\Colors\PaletteData;
use App\Support\Colors\PaletteQuota;
use App\Support\Colors\PaletteSlug;
use App\Support\Colors\PaletteTags;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria uma paleta para a conta (F4, ADR-123).
 *
 * A linha do usuario e travada antes da contagem: duas abas salvando ao mesmo
 * tempo nao passam do teto Free. O slug so nasce se a paleta ja nasce
 * publica. Colisao de slug (corrida entre contas) repete a transacao.
 */
final class CreatePalette
{
    public function __construct(
        private readonly PaletteQuota $quota,
        private readonly PaletteSlug $slug,
        private readonly PaletteTags $tags,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, PaletteData $data): ColorPalette
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $data): ColorPalette {
                    User::query()->whereKey($user->getKey())->lockForUpdate()->first();

                    if ($this->quota->reached($user)) {
                        throw ValidationException::withMessages([
                            'palette' => __('colors.validation.limit_reached'),
                        ]);
                    }

                    $slug = $data->visibility === PaletteVisibility::Public
                        ? $this->slug->unique($this->slug->base($data->name))
                        : null;

                    $palette = $user->colorPalettes()->create([
                        'name' => $data->name,
                        'description' => $data->description,
                        'visibility' => $data->visibility,
                        'kind' => $data->kind,
                        'algorithm_version' => $data->algorithmVersion,
                        'colors' => $data->colors,
                        'roles' => $data->roles,
                        'source' => $data->source,
                        'colors_count' => count($data->colors['items']),
                        'duplications_count' => 0,
                        'slug' => $slug,
                    ]);

                    $this->tags->sync($user, $palette, $data->tags);

                    return $palette->load('tags');
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }
}
