<?php

namespace App\Support\BadgeForge;

/**
 * Catalogo dos cinco estilos. Estilo desconhecido cai no default - a
 * entrada do usuario nunca gera erro.
 */
final class StyleCatalog
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(private ?string $path = null) {}

    public function get(?string $id): Style
    {
        $data = $this->data();
        $styles = $data['styles'] ?? [];
        $key = is_string($id) ? strtolower(trim($id)) : '';

        if (! isset($styles[$key])) {
            $key = (string) ($data['default'] ?? config('badgeforge.default_style', 'flat'));
        }

        if (! isset($styles[$key])) {
            $key = 'flat';
        }

        return $this->build($key, $styles[$key] ?? []);
    }

    public function default(): Style
    {
        return $this->get(null);
    }

    public function has(string $id): bool
    {
        return isset($this->data()['styles'][strtolower(trim($id))]);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        $data = $this->data();
        $order = $data['order'] ?? [];
        $ids = [];

        foreach ($order as $id) {
            if (isset($data['styles'][$id])) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function build(string $id, array $data): Style
    {
        $gradients = $this->data()['gradients'] ?? [];
        $shadow = $data['bottom_shadow'] ?? null;

        return new Style(
            id: $id,
            height: (int) ($data['height'] ?? 20),
            radius: (int) ($data['radius'] ?? 0),
            padding: (int) ($data['padding'] ?? 6),
            gap: (int) ($data['gap'] ?? 2),
            fontSize: (int) ($data['font_size'] ?? 11),
            fontWeight: (int) ($data['font_weight'] ?? 600),
            uppercase: (bool) ($data['uppercase'] ?? false),
            letterSpacing: (int) ($data['letter_spacing'] ?? 0),
            baseline: (int) ($data['baseline'] ?? 14),
            logoSize: (int) ($data['logo_size'] ?? 10),
            fontFamily: (string) ($data['font_family'] ?? 'Verdana,DejaVu Sans,sans-serif'),
            metricsFamily: (string) ($data['metrics_family'] ?? 'sans'),
            gradient: isset($data['gradient']) ? (string) $data['gradient'] : null,
            textColor: Color::parseOr($data['text_color'] ?? null, '#ffffff'),
            labelColor: isset($data['label_color']) ? Color::parse($data['label_color']) : null,
            messageColor: isset($data['message_color']) ? Color::parse($data['message_color']) : null,
            bottomShadow: is_array($shadow) ? [
                'height' => (int) ($shadow['height'] ?? 1),
                'color' => (string) ($shadow['color'] ?? '#000000'),
                'opacity' => (string) ($shadow['opacity'] ?? '.15'),
            ] : null,
            gradients: is_array($gradients) ? $gradients : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $path = $this->path ?? (string) config('badgeforge.styles_path');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return $this->data = is_array($decoded) ? $decoded : [];
    }
}
