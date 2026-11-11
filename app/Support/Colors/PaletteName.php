<?php

namespace App\Support\Colors;

use Illuminate\Support\Str;

/**
 * Nome de uma paleta duplicada (F4, ADR-124).
 *
 * O sufixo vem do namespace i18n e o resultado e truncado no mesmo limite do
 * nome original para nunca estourar a coluna.
 */
final class PaletteName
{
    public function copy(string $name): string
    {
        $suffix = (string) __('colors.palette.copy_suffix');
        $max = max(1, (int) config('colors.palettes.name_max_length', 80));
        $room = max(1, $max - strlen($suffix));

        return Str::squish(substr($name, 0, $room)).$suffix;
    }
}
