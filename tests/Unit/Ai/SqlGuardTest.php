<?php

use App\Enums\SqlDialect;
use App\Exceptions\Ai\UnsafeSqlException;
use App\Support\Ai\SqlGuard;

it('aceita SELECT e WITH em uma unica instrucao', function () {
    $guard = new SqlGuard;

    expect(fn () => $guard->assertSafe('SELECT id, name FROM users', SqlDialect::Mysql))
        ->not->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe('SELECT id FROM users;', SqlDialect::Pgsql))
        ->not->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe(
        'WITH recent AS (SELECT id FROM users) SELECT * FROM recent',
        SqlDialect::Sqlite,
    ))->not->toThrow(UnsafeSqlException::class);
});

it('ignora palavras perigosas dentro de strings e identificadores citados', function () {
    $guard = new SqlGuard;

    expect(fn () => $guard->assertSafe("SELECT 'drop table users' AS note", SqlDialect::Mysql))
        ->not->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe("SELECT 'sleep(1)' AS note", SqlDialect::Pgsql))
        ->not->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe('SELECT `update` FROM users', SqlDialect::Mysql))
        ->not->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe('SELECT "update" FROM users', SqlDialect::Mysql))
        ->not->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe('SELECT [update] FROM [users]', SqlDialect::Sqlsrv))
        ->not->toThrow(UnsafeSqlException::class);
});

it('aceita a funcao REPLACE em uma leitura', function () {
    expect(fn () => (new SqlGuard)->assertSafe(
        "SELECT replace(name, 'a', 'b') FROM users",
        SqlDialect::Mysql,
    ))->not->toThrow(UnsafeSqlException::class);
});

it('recusa DDL, DML e CTE que escreve', function () {
    $guard = new SqlGuard;
    $statements = [
        'INSERT INTO users (id) VALUES (1)',
        'UPDATE users SET name = \'x\'',
        'DELETE FROM users',
        'DROP TABLE users',
        'ALTER TABLE users ADD COLUMN email text',
        'CREATE TABLE x (id int)',
        'TRUNCATE TABLE users',
        'WITH removed AS (DELETE FROM users RETURNING id) SELECT * FROM removed',
        'WITH changed AS (UPDATE users SET name = \'x\' RETURNING id) SELECT * FROM changed',
        'EXPLAIN SELECT * FROM users',
    ];

    foreach ($statements as $sql) {
        expect(fn () => $guard->assertSafe($sql, SqlDialect::Pgsql))
            ->toThrow(UnsafeSqlException::class);
    }
});

it('recusa mais de uma instrucao e semicolon escondido em string', function () {
    $guard = new SqlGuard;

    expect(fn () => $guard->assertSafe('SELECT 1; DROP TABLE users;', SqlDialect::Mysql))
        ->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe("SELECT 'a;b' AS note", SqlDialect::Mysql))
        ->toThrow(UnsafeSqlException::class);
});

it('recusa clausulas e funcoes que tocam arquivos, locks ou o servidor', function () {
    $guard = new SqlGuard;
    $statements = [
        'SELECT * INTO backup FROM users',
        'SELECT load_file(\'/etc/passwd\')',
        'SELECT * FROM users FOR UPDATE',
        'SELECT * FROM users LOCK IN SHARE MODE',
        'SELECT pg_sleep(10)',
        'SELECT SLEEP(5)',
        'SELECT xp_cmdshell(\'dir\')',
        'SELECT nextval(\'users_id_seq\')',
        'SELECT * FROM users /*!50000 INTO OUTFILE \'/tmp/users\' */',
    ];

    foreach ($statements as $sql) {
        expect(fn () => $guard->assertSafe($sql, SqlDialect::Mysql))
            ->toThrow(UnsafeSqlException::class);
    }
});

it('recusa string ou comentario nao terminado', function () {
    $guard = new SqlGuard;

    expect(fn () => $guard->assertSafe("SELECT 'sem fim", SqlDialect::Mysql))
        ->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe('SELECT 1 /* sem fim', SqlDialect::Pgsql))
        ->toThrow(UnsafeSqlException::class);
});
