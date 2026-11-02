<?php

namespace App\Support\BadgeForge;

/**
 * Catalogo das fontes embutidas (ADR-077).
 *
 * Fonte desconhecida devolve nulo e o pedido cai na pilha do estilo - a
 * entrada do usuario nunca gera erro, como no catalogo de logos.
 */
final class BadgeFontCatalog
{
    public function find(?string $slug): ?BadgeFont
    {
        $fonts = $this->fonts();
        $key = is_string($slug) ? strtolower(trim($slug)) : '';

        if (! isset($fonts[$key])) {
            return null;
        }

        return $this->build((string) $key, $fonts[$key]);
    }

    /**
     * @return list<BadgeFont>
     */
    public function all(): array
    {
        $fonts = [];

        foreach ($this->fonts() as $slug => $data) {
            $fonts[] = $this->build((string) $slug, $data);
        }

        return $fonts;
    }

    /**
     * @return list<array{slug: string, name: string, stack: string}>
     */
    public function metadata(): array
    {
        return array_map(
            static fn (BadgeFont $font): array => [
                'slug' => $font->slug,
                'name' => $font->name,
                'stack' => $font->stack,
            ],
            $this->all(),
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function fonts(): array
    {
        $fonts = config('badgeforge.fonts', []);

        return is_array($fonts) ? $fonts : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function build(string $slug, array $data): BadgeFont
    {
        return new BadgeFont(
            slug: $slug,
            name: (string) ($data['name'] ?? $slug),
            stack: (string) ($data['stack'] ?? 'DejaVu Sans,sans-serif'),
        );
    }
}
