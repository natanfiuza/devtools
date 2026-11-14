<?php

namespace App\Actions\Colors;

use App\Models\ColorPreset;
use App\Models\User;
use App\Support\Colors\ColorPresetQuota;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria um preset do gerador para a conta (F5, ADR-093).
 *
 * A linha do usuario e travada antes da contagem, como no salvamento de
 * paleta: duas abas criando ao mesmo tempo nao passam do teto configurado.
 * Corrida no nome unico vira erro de validacao, nao 500.
 */
final class CreateColorPreset
{
    public function __construct(private readonly ColorPresetQuota $quota) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, string $name, string $base): ColorPreset
    {
        try {
            return DB::transaction(function () use ($user, $name, $base): ColorPreset {
                User::query()->whereKey($user->getKey())->lockForUpdate()->first();

                if ($this->quota->reached($user)) {
                    throw ValidationException::withMessages([
                        'preset' => __('colors.validation.preset_limit_reached'),
                    ]);
                }

                return $user->colorPresets()->create([
                    'name' => $name,
                    'base' => $base,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => __('colors.validation.preset_name_taken'),
            ]);
        }
    }
}
