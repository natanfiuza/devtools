<?php

use App\Support\Colors\PaletteRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;
use Tests\TestCase;

uses(TestCase::class);

/*
| Fixtures da importacao de design tokens (F5, Etapa 6).
|
| O parser e do navegador; o PHP nao resolve token nenhum. O que este teste
| garante e que o payload que o Vitest espera de cada fixture continua aceito
| pelas regras do servidor: escala com os 11 tons, extraida com 5/7/9/11, base
| entre as cores e papeis completos nos dois temas.
|
| O JSON esperado em `expected/` e lido pelos dois lados: o Vitest compara o
| resultado do parser e o Pest valida o mesmo objeto contra `PaletteRules`.
*/

/**
 * Caminho relativo ao arquivo, nao `base_path()`: o dataset do Pest e avaliado
 * antes da aplicacao subir.
 */
function tokenImportFixturesPath(): string
{
    return dirname(__DIR__, 2).'/Fixtures/colors/tokens';
}

/**
 * @return list<string>
 */
function tokenImportExpectedPayloads(): array
{
    $files = glob(tokenImportFixturesPath().'/expected/*.payload.json') ?: [];
    sort($files);

    return array_map(
        static fn (string $file): string => basename($file, '.payload.json'),
        $files,
    );
}

/**
 * @return array<string, mixed>
 */
function tokenImportExpected(string $name): array
{
    $path = tokenImportFixturesPath()."/expected/{$name}.payload.json";
    $contents = file_get_contents($path);

    expect($contents)->not->toBeFalse();

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * Papeis completos quando a fixture nao trouxe semanticas: e o que o cliente
 * monta pelos defaults ao salvar. Nao e a resolucao do arquivo, so a forma.
 *
 * @return array<string, array<string, string>>
 */
function tokenImportPlaceholderRoles(): array
{
    $roles = [];

    foreach (['light', 'dark'] as $theme) {
        foreach (PaletteRules::ROLES as $role) {
            $roles[$theme][$role] = '#123456';
        }
    }

    return $roles;
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function tokenImportServerPayload(array $payload): array
{
    $kind = (string) $payload['kind'];
    $isScale = $kind === 'scale';

    return [
        'name' => 'Importada',
        'description' => null,
        'visibility' => 'private',
        'kind' => $kind,
        'algorithm_version' => (int) config(
            $isScale
                ? 'colors.algorithm_versions.scale'
                : 'colors.algorithm_versions.extraction',
        ),
        'colors' => [
            'base' => $payload['base'],
            'items' => $payload['items'],
        ],
        'roles' => $payload['roles'] ?? tokenImportPlaceholderRoles(),
        'source' => $isScale
            ? null
            : ['kind' => 'file', 'label' => 'tokens.json'],
        'tags' => [],
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function tokenImportValidate(array $payload): ValidationValidator
{
    $rules = new PaletteRules;
    $isScale = (string) $payload['kind'] === 'scale';

    return Validator::make(
        tokenImportServerPayload($payload),
        array_merge(
            [
                'name' => ['required', 'string', 'max:'.(int) config('colors.palettes.name_max_length', 80)],
                'description' => ['nullable', 'string', 'max:'.(int) config('colors.palettes.description_max_length', 500)],
                'visibility' => ['required', 'in:private,public'],
                'kind' => ['required', 'in:scale,extracted'],
                'algorithm_version' => ['required', 'integer', 'min:1'],
                'tags' => ['nullable', 'array', 'max:'.(int) config('colors.tags.per_palette', 10)],
            ],
            $isScale
                ? $rules->scaleColorsRules()
                : $rules->extractedColorsRules(),
            $rules->rolesRules(),
            $rules->sourceRules(),
        ),
    );
}

it('mantem ao menos uma fixture de cada formato importavel', function () {
    $names = tokenImportExpectedPayloads();

    expect($names)
        ->toContain('native-scale', 'dtcg-scale', 'dtcg-aliases', 'style-dictionary-extracted');
});

it('tem payload esperado aceito pelas regras do servidor', function (string $name) {
    $payload = tokenImportExpected($name);
    $validator = tokenImportValidate($payload);

    expect($validator->passes())->toBeTrue(
        $name.': '.$validator->errors()->toJson(),
    );

    $kind = (string) $payload['kind'];
    $items = $payload['items'];

    expect($items)->toBeArray()->not->toBeEmpty();

    if ($kind === 'scale') {
        $tones = array_map(
            static fn (array $item): int => (int) $item['tone'],
            $items,
        );
        sort($tones);

        expect($items)->toHaveCount(count(PaletteRules::TONES))
            ->and($tones)->toBe(PaletteRules::TONES);
    } else {
        expect($items)->toHaveCount((int) $payload['tokenCount'])
            ->and(in_array(count($items), PaletteRules::EXTRACTED_COUNTS, true))->toBeTrue()
            ->and(array_column($items, 'tone'))->toBe([]);
    }

    // A base precisa ser uma das cores nas extraidas (regra do request) e
    // pode ser qualquer HEX na escala (o tom 500 nao e obrigatorio).
    $hexes = array_map(
        static fn (array $item): string => strtolower((string) $item['hex']),
        $items,
    );

    expect((string) $payload['base'])->toMatch(PaletteRules::HEX);

    if ($kind === 'extracted') {
        expect(in_array(strtolower((string) $payload['base']), $hexes, true))->toBeTrue();
    }

    if (is_array($payload['roles'] ?? null)) {
        expect(array_keys($payload['roles']))->toBe(['light', 'dark']);
    }
})->with(fn (): array => tokenImportExpectedPayloads());

it('registra no payload esperado a versao atual do algoritmo', function (string $name) {
    $payload = tokenImportExpected($name);
    $expected = (int) config(
        (string) $payload['kind'] === 'scale'
            ? 'colors.algorithm_versions.scale'
            : 'colors.algorithm_versions.extraction',
    );

    expect(tokenImportServerPayload($payload)['algorithm_version'])->toBe($expected);
})->with(fn (): array => tokenImportExpectedPayloads());
