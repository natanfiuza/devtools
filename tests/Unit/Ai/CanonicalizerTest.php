<?php

use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Exceptions\Ai\PayloadCodecException;
use App\Support\Ai\Canonicalizer;
use App\Support\Ai\CanonicalPayload;
use Tests\TestCase;

uses(TestCase::class);

it('normaliza quebras de linha e espacos do fim sem mudar o conteudo', function () {
    $payload = (new Canonicalizer)->canonicalize(
        SchemaSource::Ddl,
        SqlDialect::Mysql,
        'sql',
        "CREATE TABLE users (\r\n  id int\r\n)   \r\n",
        "Liste os usuarios  \r\n",
        'model-a',
        1,
    );

    expect($payload->schema)->toBe("CREATE TABLE users (\n  id int\n)")
        ->and($payload->description)->toBe('Liste os usuarios')
        ->and($payload->target)->toBe('sql')
        ->and($payload->model)->toBe('model-a');
});

it('gera o mesmo hash para a mesma entrada normalizada', function () {
    $canonicalizer = new Canonicalizer;

    $first = $canonicalizer->canonicalize(
        SchemaSource::Ddl,
        SqlDialect::Pgsql,
        'sql',
        "CREATE TABLE users (\n id int\n)",
        'Liste',
        'model-a',
        1,
    );

    $second = $canonicalizer->canonicalize(
        SchemaSource::Ddl,
        SqlDialect::Pgsql,
        'sql',
        "CREATE TABLE users (\r\n id int\r\n)\r\n",
        "Liste\r\n",
        'model-a',
        1,
    );

    expect($first->hash())->toBe($second->hash())
        ->and($first->json())->toBe($second->json());
});

it('muda o hash quando a versao do prompt muda', function () {
    $canonicalizer = new Canonicalizer;

    $first = $canonicalizer->canonicalize(SchemaSource::Ddl, SqlDialect::Mysql, 'sql', 'schema', 'desc', 'model', 1);
    $second = $canonicalizer->canonicalize(SchemaSource::Ddl, SqlDialect::Mysql, 'sql', 'schema', 'desc', 'model', 2);

    expect($first->hash())->not->toBe($second->hash());
});

it('ordena as chaves do json canonico', function () {
    $payload = (new Canonicalizer)->canonicalize(
        SchemaSource::Json,
        SqlDialect::Sqlite,
        'eloquent',
        '{"tables":[]}',
        'desc',
        'model',
        3,
    );

    expect(array_keys($payload->toArray()))->toBe([
        'description',
        'dialect',
        'locale',
        'model',
        'prompt_version',
        'schema',
        'schema_source',
        'target',
    ]);
});

it('muda o hash quando o locale muda', function () {
    $canonicalizer = new Canonicalizer;

    $pt = $canonicalizer->canonicalize(SchemaSource::Ddl, SqlDialect::Mysql, 'sql', 'schema', 'desc', 'model', 1, 'pt_BR');
    $en = $canonicalizer->canonicalize(SchemaSource::Ddl, SqlDialect::Mysql, 'sql', 'schema', 'desc', 'model', 1, 'en');

    expect($pt->hash())->not->toBe($en->hash())
        ->and($pt->locale)->toBe('pt_BR')
        ->and($en->locale)->toBe('en');
});

it('reconstroi o payload com fallback para locale padrao quando ausente', function () {
    $data = [
        'schema_source' => 'ddl',
        'dialect' => 'mysql',
        'target' => 'sql',
        'schema' => 'schema',
        'description' => 'desc',
        'model' => 'model',
        'prompt_version' => 1,
    ];

    $payload = CanonicalPayload::fromArray($data);

    expect($payload->locale)->toBe('en');
});

it('reconstroi o payload a partir do array canonico', function () {
    $payload = (new Canonicalizer)->canonicalize(
        SchemaSource::Migration,
        SqlDialect::Sqlsrv,
        'prisma',
        'schema',
        'desc',
        'model',
        4,
    );

    expect(CanonicalPayload::fromArray($payload->toArray())->toArray())->toBe($payload->toArray());
});

it('recusa payload reconstruido com forma invalida', function () {
    expect(fn () => CanonicalPayload::fromArray([
        'schema_source' => 'ddl',
        'dialect' => 'oracle',
        'target' => 'sql',
        'schema' => 'x',
        'description' => 'y',
        'model' => 'm',
        'prompt_version' => 1,
    ]))->toThrow(PayloadCodecException::class);
});

it('recusa entrada vazia ou alvo fora do padrao', function () {
    $canonicalizer = new Canonicalizer;

    expect(fn () => $canonicalizer->canonicalize(SchemaSource::Ddl, SqlDialect::Mysql, 'sql', '', 'desc', 'model', 1))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $canonicalizer->canonicalize(SchemaSource::Ddl, SqlDialect::Mysql, 'SQL', 'schema', 'desc', 'model', 1))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $canonicalizer->canonicalize(SchemaSource::Ddl, SqlDialect::Mysql, 'sql', 'schema', 'desc', 'model', 0))
        ->toThrow(InvalidArgumentException::class);
});
