<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgePreset;
use App\Models\User;
use App\Support\BadgeForge\BadgeForgeQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria um preset com o formulario ja normalizado (ADR-079).
 *
 * Hoje o limite e `null` (ilimitado), mas a checagem fica aqui para que mudar
 * so a configuracao baste. Mesmo padrao de trava do limite de logos.
 */
final class StoreBadgePreset
{
    public function __construct(private readonly BadgeForgeQuota $quota) {}

    /**
     * @param  array<string, mixed>  $form
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, string $name, array $form): BadgePreset
    {
        return DB::transaction(function () use ($user, $name, $form): BadgePreset {
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if ($this->quota->presetLimitReached($user)) {
                throw ValidationException::withMessages([
                    'name' => __('validation.custom_logo.limit_reached'),
                ]);
            }

            return $user->badgePresets()->create([
                'name' => $name,
                'payload' => $form,
            ]);
        });
    }
}
