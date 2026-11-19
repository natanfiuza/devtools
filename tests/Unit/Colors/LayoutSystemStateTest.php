<?php

use App\Support\Colors\LayoutSystemCatalog;
use App\Support\Colors\LayoutSystemState;
use Database\Factories\LayoutSystemFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Tests\TestCase;

uses(TestCase::class);

/*
| Estado persistido do Layout Harmonizer (F4, ADR-168).
|
| O servidor valida o retrato estrutural e deriva versoes/contagem do
| snapshot, sem recalcular o quadro.
*/

function layoutSystemStateValidator(mixed $state): ValidatorInstance
{
    $validator = Validator::make([], []);

    (new LayoutSystemState)->validate($validator, $state);

    return $validator;
}

it('aceita o estado v1 completo', function () {
    $validator = layoutSystemStateValidator(LayoutSystemFactory::stateArray());

    expect($validator->errors()->has('state'))->toBeFalse();
});

it('rejeita payload que nao e array', function (mixed $state) {
    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();
})->with([
    'nulo' => null,
    'texto' => 'state',
    'lista' => [1, 2, 3],
]);

it('rejeita versao de estado diferente da atual', function () {
    $state = LayoutSystemFactory::stateArray(['stateVersion' => 2]);

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();
});

it('rejeita snapshotVersion e schemaVersion desconhecidos', function (array $overrides) {
    $state = LayoutSystemFactory::stateArray([
        'snapshot' => $overrides,
    ]);

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();
})->with([
    'snapshot futuro' => [['snapshotVersion' => 2]],
    'schema futuro' => [['schemaVersion' => 2]],
    'algoritmo futuro' => [['algorithmVersion' => 1000]],
    'algoritmo zero' => [['algorithmVersion' => 0]],
    'sugestoes nao inteira' => [['suggestionsVersion' => 1.5]],
]);

it('rejeita entrada com estrategia desconhecida ou longa', function (array $inputs) {
    $state = LayoutSystemFactory::stateArray(['inputs' => $inputs]);

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();
})->with([
    'estrategia' => [['strategy' => 'rainbow']],
    'seed longa' => [['seedText' => str_repeat('#', 200)]],
    'fundo ausente' => [['backgroundText' => null]],
]);

it('rejeita estrategia do snapshot diferente da entrada', function () {
    $state = LayoutSystemFactory::stateArray([
        'snapshot' => ['strategy' => 'triadic'],
    ]);

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();
});

it('rejeita token fora do catalogo, familia errada e hex invalido', function (callable $mutate) {
    $state = LayoutSystemFactory::stateArray();
    $mutate($state);

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();
})->with([
    'id desconhecido' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['tokens'][0]['id'] = 'primary-unknown';
    }],
    'familia trocada' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['tokens'][0]['family'] = 'surface';
    }],
    'tema do token trocado' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['tokens'][0]['theme'] = 'dark';
    }],
    'hex curto' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['tokens'][0]['hex'] = '#fff';
    }],
    'rgba translucido' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['tokens'][0]['rgba']['a'] = 0.5;
    }],
    'canal fora da faixa' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['tokens'][0]['rgba']['r'] = 256;
    }],
    'origem desconhecida' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['tokens'][0]['source'] = 'imported';
    }],
    'token repetido' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['tokens'][1] = $state['snapshot']['themes'][0]['tokens'][0];
    }],
]);

it('rejeita checks faltando, repetidos ou com veredito incompleto', function (callable $mutate) {
    $state = LayoutSystemFactory::stateArray();
    $mutate($state);

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();
})->with([
    'par desconhecido' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['checks'][0]['background'] = 'hairline';
    }],
    'veredito sem booleano' => [function (array &$state): void {
        unset($state['snapshot']['themes'][0]['checks'][0]['verdict']['aaLarge']);
    }],
    'ratio negativo' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['checks'][0]['verdict']['ratio'] = -1;
    }],
    'check repetido' => [function (array &$state): void {
        $state['snapshot']['themes'][0]['checks'][1] = $state['snapshot']['themes'][0]['checks'][0];
    }],
    'tema faltando' => [function (array &$state): void {
        array_pop($state['snapshot']['themes']);
    }],
]);

it('exige a secundaria manual e proibe quando automatica', function () {
    $manual = LayoutSystemFactory::stateArray([
        'snapshot' => ['secondarySource' => 'manual'],
    ]);

    expect(layoutSystemStateValidator($manual)->errors()->has('state'))->toBeTrue();

    $auto = LayoutSystemFactory::stateArray([
        'secondary' => ['r' => 1, 'g' => 2, 'b' => 3],
    ]);

    expect(layoutSystemStateValidator($auto)->errors()->has('state'))->toBeTrue();

    $valid = LayoutSystemFactory::stateArray([
        'secondary' => ['r' => 1, 'g' => 2, 'b' => 3],
        'snapshot' => ['secondarySource' => 'manual'],
    ]);

    expect(layoutSystemStateValidator($valid)->errors()->has('state'))->toBeFalse();
});

it('exige fundo quando a seed original tem alpha', function () {
    $state = LayoutSystemFactory::stateArray();
    $state['snapshot']['seed']['original']['a'] = 0.5;

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();

    $state['snapshot']['seed']['background'] = ['r' => 255, 'g' => 255, 'b' => 255];

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeFalse();
});

it('rejeita overrides, locks e aceites fora do formato', function (callable $mutate) {
    $state = LayoutSystemFactory::stateArray();
    $mutate($state);

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();
})->with([
    'override de tema invalido' => [function (array &$state): void {
        $state['snapshot']['overrides'] = [[
            'theme' => 'sepia',
            'token' => 'primary',
            'origin' => 'edit',
            'rgba' => ['r' => 1, 'g' => 2, 'b' => 3, 'a' => 1],
            'hex' => '#010203',
        ]];
    }],
    'lock de token desconhecido' => [function (array &$state): void {
        $state['snapshot']['locks'] = [['theme' => 'light', 'token' => 'nope']];
    }],
    'aceite sem rgb' => [function (array &$state): void {
        $state['snapshot']['acceptedAdjustments'] = [[
            'theme' => 'light',
            'token' => 'primary',
            'target' => 'aa',
            'from' => ['r' => 1, 'g' => 2, 'b' => 3],
            'to' => ['r' => 1, 'g' => 2],
        ]];
    }],
    'override acima do teto' => [function (array &$state): void {
        $state['snapshot']['overrides'] = array_fill(0, 67, [
            'theme' => 'light',
            'token' => 'primary',
            'origin' => 'edit',
            'rgba' => ['r' => 1, 'g' => 2, 'b' => 3, 'a' => 1],
            'hex' => '#010203',
        ]);
    }],
]);

it('rejeita diagnostico malformado', function () {
    $state = LayoutSystemFactory::stateArray();
    $state['snapshot']['diagnostics'] = [[
        'theme' => 'light',
        'foreground' => 'on-primary',
        'background' => 'primary',
        'ratio' => 3,
        'aa' => true,
    ]];

    expect(layoutSystemStateValidator($state)->errors()->has('state'))->toBeTrue();
});

it('rejeita estado acima do teto de bytes', function () {
    config(['colors.layout_systems.state_max_bytes' => 200]);

    expect(layoutSystemStateValidator(LayoutSystemFactory::stateArray())->errors()
        ->has('state'))->toBeTrue();
});

it('deriva versoes e contagem de tokens do snapshot', function () {
    $state = LayoutSystemFactory::stateArray();
    $state['snapshot']['algorithmVersion'] = 7;
    $state['snapshot']['suggestionsVersion'] = 3;

    $derived = (new LayoutSystemState)->derive($state);

    expect($derived)->toMatchArray([
        'schema_version' => 1,
        'algorithm_version' => 7,
        'suggestions_version' => 3,
        'snapshot_version' => 1,
        'tokens_count' => 66,
    ])
        ->and($derived['tokens_count'])->toBe(
            count(LayoutSystemCatalog::TOKENS) * count(LayoutSystemCatalog::THEMES),
        );
});
