<?php

namespace App\Support\Colors;

use App\Models\ColorPalette;
use App\Models\User;

/**
 * Credito congelado da paleta de origem (F4, ADR-172).
 *
 * O sistema guarda a FK da origem e um retrato do que era publico no momento
 * do salvamento. Fonte privada nunca aparece no publico; fonte publica mantem
 * o texto mesmo se a paleta for excluida (o link some). O cliente envia apenas
 * `source_palette_id`; nada de credito inventado no navegador.
 */
final class LayoutSystemCredit
{
    /**
     * @return array{
     *     palette_name: string,
     *     palette_slug: string|null,
     *     palette_public: bool,
     *     author_name: string|null,
     *     author_username: string|null,
     *     captured_at: string
     * }|null
     */
    public function capture(?ColorPalette $palette): ?array
    {
        if (! $palette instanceof ColorPalette) {
            return null;
        }

        $owner = User::query()->find($palette->user_id);
        $public = $palette->isPublic() && ! $palette->isArchived();

        return [
            'palette_name' => $palette->name,
            'palette_slug' => $public ? $palette->slug : null,
            'palette_public' => $public,
            'author_name' => $owner instanceof User ? $owner->name : null,
            'author_username' => $owner instanceof User ? $owner->username : null,
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
