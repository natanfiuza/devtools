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

it('aceita instrucoes DML seguras (INSERT, UPDATE e DELETE com WHERE)', function () {
    $guard = new SqlGuard;

    expect(fn () => $guard->assertSafe(
        "INSERT INTO users (name, email) VALUES ('John', 'john@example.com')",
        SqlDialect::Mysql,
    ))->not->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe(
        "UPDATE users SET name = 'John Doe', updated_at = NOW() WHERE id = 1;",
        SqlDialect::Pgsql,
    ))->not->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe(
        'DELETE FROM users WHERE active = 0',
        SqlDialect::Sqlite,
    ))->not->toThrow(UnsafeSqlException::class);
});

it('recusa UPDATE e DELETE sem clausula WHERE', function () {
    $guard = new SqlGuard;

    expect(fn () => $guard->assertSafe(
        "UPDATE users SET name = 'x'",
        SqlDialect::Mysql,
    ))->toThrow(UnsafeSqlException::class);

    expect(fn () => $guard->assertSafe(
        'DELETE FROM users',
        SqlDialect::Pgsql,
    ))->toThrow(UnsafeSqlException::class);
});

it('recusa DDL e comandos administrativos', function () {
    $guard = new SqlGuard;
    $statements = [
        'DROP TABLE users',
        'ALTER TABLE users ADD COLUMN email text',
        'CREATE TABLE x (id int)',
        'TRUNCATE TABLE users',
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
