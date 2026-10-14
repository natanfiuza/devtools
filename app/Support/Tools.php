<?php

namespace App\Support;

/**
 * Leitura do registro de tools (docs/SPEC.md secao 4).
 *
 * O frontend le os mesmos arquivos pelo `import.meta.glob` do Vite; aqui so
 * o que o servidor precisa saber: quais tools existem, qual o status e a
 * ordem. O sitemap usa `available()` - tool que ainda nao funciona nao entra
 * no indice.
 *
 * Texto visivel nao mora no manifest: as chaves saem por convencao
 * (`tools.<slug>.name`, `tools.<slug>.description`, `tools.<slug>.keywords`).
 */
final class Tools
{
    /**
     * Tools registradas, na ordem do manifest.
     *
     * @return list<array{slug: string, status: string, order: int}>
     */
    public static function all(): array
    {
        $directory = (string) config('tools.path');
        $files = glob(rtrim($directory, '/\\').'/*/manifest.json');
        $tools = [];

        foreach ($files === false ? [] : $files as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            $decoded = json_decode($contents, true);
            $slug = is_array($decoded) ? ($decoded['slug'] ?? null) : null;
            $status = is_array($decoded) ? ($decoded['status'] ?? null) : null;
            $order = is_array($decoded) ? ($decoded['order'] ?? 0) : 0;

            if (! is_string($slug) || ! is_string($status)) {
                continue;
            }

            $tools[] = [
                'slug' => $slug,
                'status' => $status,
                'order' => is_int($order) ? $order : 0,
            ];
        }

        usort($tools, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        // `usort` reindexa: ja sai lista na ordem de exibicao.
        return $tools;
    }

    /**
     * Tools que ja funcionam - as unicas que entram no sitemap.
     *
     * @return list<array{slug: string, status: string, order: int}>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $tool): bool => $tool['status'] === 'available',
        ));
    }
}
