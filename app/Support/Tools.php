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
     * `hidden` fica de fora, como no leitor TypeScript
     * (resources/js/tools/registry.ts): a tool escondida nao aparece para o
     * visitante em leitor nenhum - nem na home, nem no sitemap, nem no cartao
     * de compartilhamento, que desenha os nomes a partir daqui.
     *
     * As `keywords` vem junto porque sao elas que desenham a imagem de
     * compartilhamento de cada tool (app/Console/Commands/GenerateOgImage.php):
     * termos do proprio manifest, sem frase em idioma nenhum.
     *
     * @return list<array{slug: string, status: string, group: string|null, order: int, keywords: list<string>}>
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
            $group = is_array($decoded) ? ($decoded['group'] ?? null) : null;
            $order = is_array($decoded) ? ($decoded['order'] ?? 0) : 0;

            /** @var list<string> $keywords */
            $keywords = array_values(array_filter(
                is_array($decoded) ? ($decoded['keywords'] ?? []) : [],
                'is_string',
            ));

            if (! is_string($slug) || ! is_string($status) || $status === 'hidden') {
                continue;
            }

            $tools[] = [
                'slug' => $slug,
                'status' => $status,
                'group' => is_string($group) && $group !== '' ? $group : null,
                'order' => is_int($order) ? $order : 0,
                'keywords' => $keywords,
            ];
        }

        usort($tools, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        // `usort` reindexa: ja sai lista na ordem de exibicao.
        return $tools;
    }

    /**
     * Tools que ja funcionam - as unicas que entram no sitemap e as unicas com
     * imagem de compartilhamento propria.
     *
     * @return list<array{slug: string, status: string, group: string|null, order: int, keywords: list<string>}>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $tool): bool => $tool['status'] === 'available',
        ));
    }

    /**
     * Uma tool pelo slug, ou `null` se ela nao existe.
     *
     * Le `all()`, entao a tool `hidden` nao e encontrada - e o mesmo `null` do
     * slug desconhecido, que a pagina de tool transforma no mesmo 404 (ADR-007).
     *
     * O slug vem cru da URL: quem monta nome de arquivo a partir dele precisa
     * validar o formato antes (`resources/js/tools/<slug>/manifest.json`).
     *
     * @return array{slug: string, status: string, group: string|null, order: int, keywords: list<string>}|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $tool) {
            if ($tool['slug'] === $slug) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * A tool que a URL pede - o par slug + grupo, sem tolerancia.
     *
     * Esta e a unica regra que decide qual URL de tool existe (ADR-116): tool
     * com grupo so responde na URL aninhada, tool sem grupo so na plana. O
     * `null` do grupo e o da rota `tools.show`; o grupo da URL agrupada chega
     * como string. Os dois lados sao comparados com o registro - nunca com a
     * URL crua - e o que nao casa da o mesmo 404 do slug desconhecido.
     *
     * @return array{slug: string, status: string, group: string|null, order: int, keywords: list<string>}|null
     */
    public static function findForUrl(string $slug, ?string $group): ?array
    {
        foreach (self::all() as $tool) {
            if ($tool['slug'] === $slug && $tool['group'] === $group) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Os grupos de URL em uso, na ordem do catalogo.
     *
     * @return list<string>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (self::all() as $tool) {
            if ($tool['group'] !== null) {
                $groups[$tool['group']] = true;
            }
        }

        return array_keys($groups);
    }

    /**
     * Nome da rota e parametros de uma tool, com ou sem grupo.
     *
     * E o que o sitemap usa para montar a URL de cada tool sem repetir a regra
     * do `findForUrl` em cada leitor.
     *
     * @param  array{slug: string, group: string|null}  $tool
     * @return array{0: string, 1: array<string, string>}
     */
    public static function routeFor(array $tool): array
    {
        return $tool['group'] === null
            ? ['tools.show', ['slug' => $tool['slug']]]
            : ['tools.show.grouped', ['group' => $tool['group'], 'slug' => $tool['slug']]];
    }
}
