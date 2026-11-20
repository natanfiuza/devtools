<?php

use App\Support\Colors\LayoutSystemCatalog;
use App\Support\Colors\LayoutSystemExportMessages;
use App\Support\Locales;
use Tests\TestCase;

uses(TestCase::class);

/*
| Payload de traducao das exportacoes (F5, ADR-175).
|
| O cliente so recebe o idioma ativo, mas o Markdown sai no idioma escolhido;
| por isso o servidor manda as cinco locales, so com o subtree que os
| exportadores usam. Todas precisam ter a mesma forma: chave a mais numa delas
| quebraria o documento de quem escolheu aquele idioma.
*/

/**
 * Chaves folha (notacao de ponto) de um array aninhado, ordenadas.
 *
 * @param  array<string, mixed>  $values
 * @return list<string>
 */
function layoutExportLeafKeys(array $values, string $prefix = ''): array
{
    $keys = [];

    foreach ($values as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $keys = array_merge($keys, layoutExportLeafKeys($value, $full));

            continue;
        }

        $keys[] = $full;
    }

    sort($keys);

    return $keys;
}

it('entrega uma entrada por idioma suportado, so com o subtree usado', function () {
    $messages = LayoutSystemExportMessages::all();

    expect(array_keys($messages))->toBe(Locales::codes());

    foreach ($messages as $locale => $blocks) {
        expect(array_keys($blocks))->toEqualCanonicalizing([
            'export',
            'tokens',
            'strategy',
            'provenance',
            'families',
            'view',
            'contrast',
            'seed',
        ], "Subarvores fora do contrato em {$locale}");

        expect(array_keys($blocks['seed']))->toBe(['summary']);
    }
});

it('tem os 33 tokens e as 4 estrategias em todos os idiomas', function () {
    foreach (LayoutSystemExportMessages::all() as $locale => $blocks) {
        expect(array_keys($blocks['tokens']))->toEqualCanonicalizing(
            LayoutSystemCatalog::tokenIds(),
            "Tokens divergentes em {$locale}",
        );

        expect($blocks['strategy'])->toHaveKeys(
            LayoutSystemCatalog::STRATEGIES,
            "Estrategias divergentes em {$locale}",
        );
    }
});

it('tem o grupo export com as mesmas folhas nos 5 idiomas', function () {
    $reference = layoutExportLeafKeys(LayoutSystemExportMessages::all()[Locales::fallback()]['export']);

    expect($reference)->not->toBeEmpty();

    foreach (LayoutSystemExportMessages::all() as $locale => $blocks) {
        expect(layoutExportLeafKeys($blocks['export']))->toBe(
            $reference,
            "Chaves de export divergentes em {$locale}",
        );
    }
});

it('nao deixa traducao vazia no subtree exportado', function () {
    foreach (LayoutSystemExportMessages::all() as $locale => $blocks) {
        foreach (layoutExportLeafKeys($blocks, '') as $key) {
            expect(trim((string) data_get($blocks, $key)))->not->toBe(
                '',
                "Traducao vazia: {$locale}.{$key}",
            );
        }
    }
});
