<?php

namespace App\Support\Colors;

use App\Enums\PaletteKind;
use App\Enums\PaletteVisibility;

/**
 * Payload validado de uma paleta (F4, ADR-123).
 *
 * Transita entre requests e actions sem recalcular nada: as cores efetivas e
 * os papeis chegam prontos do navegador.
 */
final class PaletteData
{
    /**
     * @param  array{base: string, items: list<array{hex: string, tone?: int}>}  $colors
     * @param  array{light: array<string, string>, dark: array<string, string>}  $roles
     * @param  array<string, string>|null  $source
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly PaletteVisibility $visibility,
        public readonly PaletteKind $kind,
        public readonly int $algorithmVersion,
        public readonly array $colors,
        public readonly array $roles,
        public readonly ?array $source,
        public readonly array $tags,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{base: string, items: list<array{hex: string, tone?: int}>} $colors */
        $colors = $data['colors'];

        /** @var array{light: array<string, string>, dark: array<string, string>} $roles */
        $roles = $data['roles'];

        /** @var array<string, string>|null $source */
        $source = $data['source'] ?? null;

        /** @var list<string> $tags */
        $tags = $data['tags'] ?? [];

        return new self(
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            visibility: PaletteVisibility::from((string) $data['visibility']),
            kind: PaletteKind::from((string) $data['kind']),
            algorithmVersion: (int) $data['algorithm_version'],
            colors: $colors,
            roles: $roles,
            source: $source,
            tags: $tags,
        );
    }
}
