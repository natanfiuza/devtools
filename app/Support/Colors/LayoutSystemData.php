<?php

namespace App\Support\Colors;

use App\Enums\PaletteVisibility;

/**
 * Payload validado de um sistema de layout (F4, ADR-168).
 *
 * O `state` chega pronto do navegador (snapshot + entradas) e nao e
 * recalculado no servidor; as versoes persistidas sao derivadas dele.
 */
final class LayoutSystemData
{
    /**
     * @param  array<string, mixed>  $state
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly PaletteVisibility $visibility,
        public readonly array $state,
        public readonly array $tags,
        public readonly ?string $sourcePaletteId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $state */
        $state = $data['state'];

        /** @var list<string> $tags */
        $tags = $data['tags'] ?? [];

        return new self(
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            visibility: PaletteVisibility::from((string) $data['visibility']),
            state: $state,
            tags: $tags,
            sourcePaletteId: isset($data['source_palette_id']) ? (string) $data['source_palette_id'] : null,
        );
    }
}
