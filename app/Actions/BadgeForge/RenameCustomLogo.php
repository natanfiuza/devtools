<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeCustomLogo;

/**
 * Renomeia um logo personalizado (F3, ADR-078; API F9, ADR-090).
 *
 * O nome e o unico campo mutavel: paths e `viewBox` sao o desenho em si e
 * trocar o conteudo seria outro logo (reenviar e a saida). A acao existe para
 * a tela e a API compartilharem a mesma mutacao.
 */
final class RenameCustomLogo
{
    public function __invoke(BadgeCustomLogo $logo, string $name): BadgeCustomLogo
    {
        $logo->forceFill(['name' => $name])->save();

        return $logo;
    }
}
