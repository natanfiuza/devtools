<?php

use App\Actions\Colors\CreateLayoutSystem;
use App\Models\User;
use App\Support\Colors\LayoutSystemData;
use Database\Factories\LayoutSystemFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Concorrência da cota conjunta (F6, ADR-170/ADR-178).
|
| CreateLayoutSystem trava a linha do usuário (SELECT ... FOR UPDATE) antes de
| contar paletas + sistemas; é esse lock que impede duas criações simultâneas
| de furarem o teto. O sqlite do suite não tem lock de linha, então este teste
| é opt-in e só roda contra um banco MySQL/MariaDB descartável:
|
|   DB_CONNECTION=mysql DB_DATABASE=devtools_test \
|   DB_USERNAME=... DB_PASSWORD=... LAYOUT_CONCURRENCY_TEST=1 \
|   php artisan test --filter=LayoutSystemConcurrencyTest
|
| Sem LAYOUT_CONCURRENCY_TEST=1, driver MySQL/MariaDB e banco terminado em
| `_test` o teste é skipped — nunca aponta para dev/produção.
*/

function requireDisposableMysql(): void
{
    if (getenv('LAYOUT_CONCURRENCY_TEST') !== '1') {
        test()->markTestSkipped(
            'Defina LAYOUT_CONCURRENCY_TEST=1 para rodar o teste de concorrência.',
        );
    }

    $connection = DB::connection();
    $driver = $connection->getDriverName();
    $database = (string) $connection->getDatabaseName();

    if (! in_array($driver, ['mysql', 'mariadb'], true)) {
        test()->markTestSkipped(
            "Driver {$driver}: o teste de concorrência exige MySQL/MariaDB.",
        );
    }

    if (! str_ends_with(strtolower($database), '_test')) {
        test()->markTestSkipped(
            'O banco precisa terminar em `_test` (banco descartável).',
        );
    }
}

it('bloqueia a segunda transacao enquanto a linha do usuario esta travada', function () {
    requireDisposableMysql();

    $base = config('database.connections.'.DB::getDefaultConnection());

    config([
        'database.connections.lock_a' => $base,
        'database.connections.lock_b' => $base,
    ]);

    $user = User::factory()->create();
    $a = DB::connection('lock_a');
    $b = DB::connection('lock_b');

    $lockRow = fn ($connection) => $connection
        ->table('users')
        ->where('id', $user->getKey())
        ->lockForUpdate()
        ->first();

    try {
        $a->statement('SET SESSION innodb_lock_wait_timeout = 10');
        $b->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $a->beginTransaction();
        expect($lockRow($a))->not->toBeNull();

        $b->beginTransaction();
        $blockedAt = microtime(true);
        $blocked = false;

        try {
            $lockRow($b);
        } catch (QueryException $exception) {
            $blocked = str_contains(
                strtolower($exception->getMessage()),
                'lock wait timeout',
            );
        }

        $waited = microtime(true) - $blockedAt;

        $b->rollBack();

        expect($blocked)->toBeTrue()
            ->and($waited)->toBeGreaterThanOrEqual(0.9);

        $a->commit();

        $b->beginTransaction();
        $acquiredAt = microtime(true);

        expect($lockRow($b))->not->toBeNull()
            ->and(microtime(true) - $acquiredAt)->toBeLessThan(0.9);

        $b->commit();
    } finally {
        while ($a->transactionLevel() > 0) {
            $a->rollBack();
        }

        while ($b->transactionLevel() > 0) {
            $b->rollBack();
        }

        DB::purge('lock_a');
        DB::purge('lock_b');

        $user->forceDelete();
    }
});

it('faz a criacao real esperar o lock da linha do usuario', function () {
    requireDisposableMysql();

    $original = DB::getDefaultConnection();
    $base = config('database.connections.'.$original);

    config([
        'database.connections.lock_a' => $base,
        'database.connections.lock_b' => $base,
    ]);

    $user = User::factory()->create();
    $a = DB::connection('lock_a');

    $data = LayoutSystemData::fromArray([
        'name' => 'Sistema Concorrente',
        'description' => null,
        'visibility' => 'private',
        'tags' => [],
        'state' => LayoutSystemFactory::stateArray(),
        'source_palette_id' => null,
    ]);

    try {
        $a->statement('SET SESSION innodb_lock_wait_timeout = 10');
        $a->beginTransaction();
        $a->table('users')->where('id', $user->getKey())->lockForUpdate()->first();

        // A action usa a conexao padrao; apontar para a segunda conexao deixa
        // o lock da linha (A) bloquear exatamente o caminho de criacao real.
        DB::setDefaultConnection('lock_b');
        DB::connection('lock_b')->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;

        try {
            app(CreateLayoutSystem::class)($user, $data);
        } catch (QueryException $exception) {
            $blocked = str_contains(
                strtolower($exception->getMessage()),
                'lock wait timeout',
            );
        }

        expect($blocked)->toBeTrue();

        $a->commit();

        $system = app(CreateLayoutSystem::class)($user, $data);

        expect($system->exists)->toBeTrue();
    } finally {
        while ($a->transactionLevel() > 0) {
            $a->rollBack();
        }

        DB::setDefaultConnection($original);
        DB::purge('lock_a');
        DB::purge('lock_b');

        $user->forceDelete();
    }
});
