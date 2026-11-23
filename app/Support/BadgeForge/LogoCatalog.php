<?php

namespace App\Support\BadgeForge;

/**
 * Resolve slug -> logo embutido. Slug fora do registro, arquivo ausente ou
 * SVG com tag fora de <svg>/<path> viram null (o badge segue sem logo).
 */
final class LogoCatalog
{
    /** @var array<string, Logo|null> */
    private array $cache = [];

    public function __construct(private ?string $directory = null) {}

    public function find(mixed $slug): ?Logo
    {
        if (! is_string($slug)) {
            return null;
        }

        $slug = strtolower(trim($slug));

        if ($slug === '') {
            return null;
        }

        if (array_key_exists($slug, $this->cache)) {
            return $this->cache[$slug];
        }

        return $this->cache[$slug] = $this->load($slug);
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    public function metadata(): array
    {
        $logos = [];

        foreach ((array) config('badgeforge.logos', []) as $slug => $definition) {
            $logos[] = [
                'slug' => (string) $slug,
                'name' => (string) ($definition['name'] ?? $slug),
            ];
        }

        return $logos;
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_map(
            static fn (array $logo): string => $logo['slug'],
            $this->metadata(),
        );
    }

    private function load(string $slug): ?Logo
    {
        $definition = config('badgeforge.logos.'.$slug);

        if (! is_array($definition)) {
            return null;
        }

        $directory = $this->directory ?? (string) config('badgeforge.logos_path');
        $file = $directory.'/'.(string) ($definition['file'] ?? $slug.'.svg');

        if (! is_file($file)) {
            return null;
        }

        $svg = (string) file_get_contents($file);

        if (preg_match('/<svg\b[^>]*\bviewBox="([^"]+)"/i', $svg, $viewBox) !== 1) {
            return null;
        }

        if (preg_match_all('/<path\b([^>]*)>/i', $svg, $pathMatches) < 1) {
            return null;
        }

        preg_match_all('/<\s*\/?\s*([a-zA-Z][a-zA-Z0-9:-]*)/', $svg, $tags);

        foreach ($tags[1] as $tag) {
            if (! in_array(strtolower($tag), ['svg', 'path'], true)) {
                return null;
            }
        }

        $paths = [];

        foreach ($pathMatches[1] as $attributes) {
            if (preg_match('/\bd="([^"]+)"/i', $attributes, $dMatch) !== 1) {
                return null;
            }

            $paths[] = new LogoPath(
                d: $dMatch[1],
                fillRule: $this->extractRule($attributes, 'fill-rule'),
                clipRule: $this->extractRule($attributes, 'clip-rule'),
            );
        }

        return new Logo(
            slug: $slug,
            name: (string) ($definition['name'] ?? $slug),
            viewBox: $viewBox[1],
            paths: $paths,
        );
    }

    private function extractRule(string $attributes, string $attribute): ?string
    {
        if (preg_match('/\b'.preg_quote($attribute, '/').'="(nonzero|evenodd)"/i', $attributes, $match) !== 1) {
            return null;
        }

        return strtolower($match[1]);
    }
}
