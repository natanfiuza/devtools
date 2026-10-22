<?php

use App\Enums\SqlDialect;
use App\Exceptions\Ai\InvalidContractException;
use App\Support\Ai\ContractValidator;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function aiContractFixture(array $overrides = []): array
{
    return array_replace([
        'sql' => 'SELECT id FROM users',
        'dialect' => 'mysql',
        'assumptions' => [],
        'warnings' => [],
        'tables_used' => ['users'],
        'needs_clarification' => false,
        'clarification' => null,
        'outputs' => ['sql' => 'SELECT id FROM users'],
    ], $overrides);
}

beforeEach(function () {
    config(['ai.limits.max_output_bytes' => 32768]);
});

it('aceita e normaliza o contrato completo', function () {
    $result = (new ContractValidator)->validate(aiContractFixture([
        'title' => ' Busca por Usuários ',
        'sql' => "  SELECT id FROM users;\n",
        'dialect' => ' MYSQL ',
        'assumptions' => [' um ', '', 'dois'],
        'outputs' => ['sql' => " SELECT id FROM users; \n"],
    ]), SqlDialect::Mysql, 'sql');

    expect($result->title)->toBe('Busca por Usuários')
        ->and($result->sql)->toBe('SELECT id FROM users;')
        ->and($result->dialect)->toBe(SqlDialect::Mysql)
        ->and($result->assumptions)->toBe(['um', 'dois'])
        ->and($result->warnings)->toBe([])
        ->and($result->tablesUsed)->toBe(['users'])
        ->and($result->needsClarification)->toBeFalse()
        ->and($result->clarification)->toBeNull()
        ->and($result->outputs['sql'])->toBe('SELECT id FROM users;');
});

it('aceita clarification com sql vazio', function () {
    $result = (new ContractValidator)->validate(aiContractFixture([
        'sql' => '  ',
        'needs_clarification' => true,
        'clarification' => ' Qual tabela devo usar? ',
        'outputs' => [],
    ]), SqlDialect::Mysql, 'sql');

    expect($result->needsClarification)->toBeTrue()
        ->and($result->sql)->toBe('')
        ->and($result->clarification)->toBe('Qual tabela devo usar?')
        ->and($result->outputs)->toBe([]);
});

it('recusa campo obrigatorio ausente', function () {
    $data = aiContractFixture();
    unset($data['warnings']);

    expect(fn () => (new ContractValidator)->validate($data, SqlDialect::Mysql, 'sql'))
        ->toThrow(InvalidContractException::class);
});

it('recusa dialeto diferente do pedido', function () {
    expect(fn () => (new ContractValidator)->validate(
        aiContractFixture(['dialect' => 'pgsql']),
        SqlDialect::Mysql,
        'sql',
    ))->toThrow(InvalidContractException::class);
});

it('recusa sql vazio ou saida do alvo ausente', function () {
    $validator = new ContractValidator;

    expect(fn () => $validator->validate(aiContractFixture(['sql' => '  ']), SqlDialect::Mysql, 'sql'))
        ->toThrow(InvalidContractException::class);

    expect(fn () => $validator->validate(
        aiContractFixture(['outputs' => ['eloquent' => 'User::query()']]),
        SqlDialect::Mysql,
        'sql',
    ))->toThrow(InvalidContractException::class);
});

it('recusa contradicoes entre clarification e sql', function () {
    $validator = new ContractValidator;

    expect(fn () => $validator->validate(aiContractFixture([
        'needs_clarification' => true,
        'clarification' => 'Qual tabela?',
    ]), SqlDialect::Mysql, 'sql'))->toThrow(InvalidContractException::class);

    expect(fn () => $validator->validate(aiContractFixture([
        'sql' => '',
        'needs_clarification' => true,
        'clarification' => null,
    ]), SqlDialect::Mysql, 'sql'))->toThrow(InvalidContractException::class);
});

it('recusa listas, booleanos e outputs com tipo errado', function () {
    $validator = new ContractValidator;
    $cases = [
        aiContractFixture(['warnings' => 'aviso']),
        aiContractFixture(['assumptions' => ['ok' => 'nao e lista']]),
        aiContractFixture(['needs_clarification' => 'false']),
        aiContractFixture(['outputs' => ['SQL Builder' => 'SELECT 1']]),
        aiContractFixture(['outputs' => ['sql' => ['SELECT 1']]]),
    ];

    foreach ($cases as $data) {
        expect(fn () => $validator->validate($data, SqlDialect::Mysql, 'sql'))
            ->toThrow(InvalidContractException::class);
    }
});

it('recusa resposta maior que o teto de bytes', function () {
    config(['ai.limits.max_output_bytes' => 20]);

    expect(fn () => (new ContractValidator)->validate(
        aiContractFixture(),
        SqlDialect::Mysql,
        'sql',
    ))->toThrow(InvalidContractException::class);
});

it('aceita e normaliza o campo explanation opcional', function () {
    $validator = new ContractValidator;

    $resultWithExplanation = $validator->validate(
        aiContractFixture(['explanation' => '  Esta consulta busca usuarios ativos agrupados por data.  ']),
        SqlDialect::Mysql,
        'sql',
    );
    expect($resultWithExplanation->explanation)->toBe('Esta consulta busca usuarios ativos agrupados por data.');

    $resultWithoutExplanation = $validator->validate(
        aiContractFixture(['explanation' => null]),
        SqlDialect::Mysql,
        'sql',
    );
    expect($resultWithoutExplanation->explanation)->toBeNull();

    $resultEmptyExplanation = $validator->validate(
        aiContractFixture(['explanation' => '   ']),
        SqlDialect::Mysql,
        'sql',
    );
    expect($resultEmptyExplanation->explanation)->toBeNull();
});

it('recusa explanation com tipo invalido ou longo demais', function () {
    $validator = new ContractValidator;

    expect(fn () => $validator->validate(
        aiContractFixture(['explanation' => 12345]),
        SqlDialect::Mysql,
        'sql',
    ))->toThrow(InvalidContractException::class);

    expect(fn () => $validator->validate(
        aiContractFixture(['explanation' => str_repeat('a', 4001)]),
        SqlDialect::Mysql,
        'sql',
    ))->toThrow(InvalidContractException::class);
});
