<?php

namespace App\Support\BadgeForge;

/**
 * Logo ja validado: viewBox e lista de paths.
 *
 * Os logos embutidos tem exatamente um path; os personalizados (F3) podem ter
 * varios. `custom` distingue os dois na hora de montar a UI, mas o renderer
 * trata os dois igual.
 */
final readonly class Logo
{
    /**
     * @param  list<LogoPath>  $paths
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $viewBox,
        public array $paths,
        public bool $custom = false,
    ) {}
}
