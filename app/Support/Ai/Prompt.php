<?php

namespace App\Support\Ai;

/**
 * Prompt de um alvo, ja lido do disco e com a versao extraida do nome.
 *
 * O caminho (`<slug>.v<N>.md`) e o que a coluna `output_targets.prompt_template`
 * guarda; a versao entra no payload canonico, entao trocar o arquivo sem trocar
 * a versao nao reutiliza cache antigo por engano (ADR-016).
 */
final readonly class Prompt
{
    public function __construct(
        public string $template,
        public string $slug,
        public int $version,
        public string $text,
    ) {}
}
