<?php

use App\Support\Locales;
use App\Support\Tools;

/*
| O registro de tools tem uma fonte so: `resources/js/tools/<slug>/manifest.json`.
| O TypeScript le pelo Vite (home e busca) e o PHP le por App\Support\Tools
| (sitemap). Este teste guarda o contrato dos dois lados: os campos, os slugs do
| MVP, a traducao que casa com o slug e o filtro que o sitemap usa.
*/

/** Slugs do MVP, na ordem de exibicao (docs/SPEC.md secao 5). */
function mvpToolSlugs(): array
{
    return [
        'json-viewer',
        'ai-sql-builder',
        'json-to-code',
        'sql-formatter',
        'base64-url-hash',
        'jwt-decoder',
        'timestamp-uuid',
        'regex-tester',
    ];
}

/** @return list<string> */
function toolManifestPaths(): array
{
    $paths = glob(config('tools.path').'/*/manifest.json');

    return $paths === false ? [] : $paths;
}

/** @return array<string, mixed>|null */
function readToolManifest(string $path): ?array
{
    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : null;
}

it('registra exatamente as tools do MVP, na ordem de exibicao', function () {
    expect(array_column(Tools::all(), 'slug'))->toBe(mvpToolSlugs());
});

it('guarda um manifest por tool, com o slug igual ao nome do diretorio', function () {
    $slugs = [];

    foreach (toolManifestPaths() as $path) {
        $manifest = readToolManifest($path);
        $directory = basename(dirname($path));

        expect($manifest)->not->toBeNull("Manifest invalido em {$directory}");
        expect($manifest['slug'] ?? null)->toBe($directory);
        expect($manifest['status'] ?? null)->toBeIn(['soon', 'available', 'hidden']);
        expect($manifest['category'] ?? null)->toBeString()->not->toBeEmpty();
        expect($manifest['icon'] ?? null)->toBeString()->not->toBeEmpty();
        expect($manifest['order'] ?? null)->toBeInt();
        expect($manifest['keywords'] ?? null)->toBeArray()->not->toBeEmpty();

        foreach ($manifest['keywords'] as $keyword) {
            expect($keyword)->toBeString()->not->toBeEmpty();
        }

        $slugs[] = $directory;
    }

    expect($slugs)->toHaveCount(count(mvpToolSlugs()));
});

it('nao repete slug nem ordem de exibicao', function () {
    $tools = Tools::all();

    expect(array_unique(array_column($tools, 'slug')))->toHaveCount(count($tools));
    expect(array_unique(array_column($tools, 'order')))->toHaveCount(count($tools));
});

/*
| F2: a primeira tool funciona. O selo "em breve" fica com as outras sete, e o
| sitemap - que lista so o que funciona - passa a ter a pagina dela.
*/
it('lista para o sitemap apenas as tools que funcionam', function () {
    expect(array_column(Tools::available(), 'slug'))->toBe(['json-viewer']);
});

it('ordena pelo campo order e filtra as que funcionam', function () {
    config(['tools.path' => base_path('tests/Fixtures/tools')]);

    // A fixture tem uma tool disponivel, uma "soon", uma "hidden" e um manifest
    // sem slug. As tres situacoes de status estao la porque a pagina da tool
    // cobra o 404 de cada uma (ToolPageTest).
    expect(array_column(Tools::all(), 'slug'))->toBe(['alpha-tool', 'zeta-tool', 'hidden-tool']);
    expect(array_column(Tools::available(), 'slug'))->toBe(['zeta-tool']);
});

it('traduz nome, descricao e palavras-chave de cada slug nos 5 idiomas', function () {
    foreach (Locales::codes() as $code) {
        foreach (Tools::all() as $tool) {
            foreach (['name', 'description', 'keywords'] as $field) {
                $value = trans("tools.{$tool['slug']}.{$field}", [], $code);

                expect(is_string($value) && trim($value) !== '')
                    ->toBeTrue("Falta tools.{$tool['slug']}.{$field} em {$code}");
            }
        }
    }
});

it('traduz a introducao de cada tool que funciona', function () {
    // A pagina da tool mostra `tools.<slug>.intro`; so as que funcionam tem
    // pagina, entao so delas se cobra a introducao. O `trans` devolve a propria
    // chave quando ela nao existe: e contra isso que a comparacao guarda.
    foreach (Locales::codes() as $code) {
        foreach (Tools::available() as $tool) {
            $key = "tools.{$tool['slug']}.intro";
            $value = trans($key, [], $code);

            expect($value)->toBeString()->not->toBe($key, "Falta {$key} em {$code}");
        }
    }
});

it('traduz o rotulo de cada categoria usada e o selo do status', function () {
    $categories = [];

    foreach (toolManifestPaths() as $path) {
        $manifest = readToolManifest($path);

        if ($manifest !== null && is_string($manifest['category'] ?? null)) {
            $categories[$manifest['category']] = true;
        }
    }

    foreach (Locales::codes() as $code) {
        foreach (array_keys($categories) as $category) {
            $label = trans("tools.categories.{$category}", [], $code);

            expect(is_string($label) && trim($label) !== '')
                ->toBeTrue("Falta tools.categories.{$category} em {$code}");
        }

        expect(trans('tools.status.soon', [], $code))->toBeString()->not->toBeEmpty();
    }
});

/*
| O manifest guarda o nome do icone; o componente vive em `icons.ts`. Se um
| nome novo entrar no JSON e nao no mapa, o card sai sem icone (e o PHP nem
| percebe) - entao o teste le as chaves do mapa e cobra que todo manifest
| aponte para uma delas.
*/
it('aponta para icones que existem no mapa do frontend', function () {
    $map = (string) file_get_contents(resource_path('js/tools/icons.ts'));

    preg_match_all("/^\s*'?([a-z][a-z-]*)'?:\s*[A-Z]/m", $map, $matches);
    $known = $matches[1];

    expect($known)->not->toBeEmpty();

    foreach (toolManifestPaths() as $path) {
        $icon = readToolManifest($path)['icon'] ?? null;

        expect(is_string($icon) && in_array($icon, $known, true))
            ->toBeTrue("Icone '{$icon}' fora de resources/js/tools/icons.ts");
    }
});
