<?php

namespace App\Support\BadgeForge;

/**
 * Logo embutido ja validado: um unico path e o viewBox do arquivo.
 */
final readonly class Logo
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $viewBox,
        public string $pathData,
    ) {}
}
