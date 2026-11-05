<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
| Agendamento da manutencao do analytics (F7, ADR-086): agregacao de hora em
| hora e poda diaria, aplicadas por migration aditiva e idempotente.
*/

/**
 * @return list<string>
 */
function analyticsScheduleCommands(): array
{
    return DB::table('task_schedules')
        ->whereIn('command', [
            'badgeforge:analytics:aggregate',
            'badgeforge:analytics:prune',
        ])
        ->orderBy('command')
        ->pluck('command')
        ->map(static fn (mixed $command): string => (string) $command)
        ->all();
}

it('agenda a agregacao horaria e a poda diaria', function () {
    $rows = DB::table('task_schedules')
        ->whereIn('command', [
            'badgeforge:analytics:aggregate',
            'badgeforge:analytics:prune',
        ])
        ->orderBy('command')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->command)->toBe('badgeforge:analytics:aggregate')
        ->and($rows[0]->expression)->toBe('13 * * * *')
        ->and($rows[1]->command)->toBe('badgeforge:analytics:prune')
        ->and($rows[1]->expression)->toBe('17 4 * * *')
        ->and((bool) $rows[0]->is_active)->toBeTrue()
        ->and((bool) $rows[1]->is_active)->toBeTrue();
});

it('e idempotente ao reaplicar a migration', function () {
    $migration = require database_path('migrations/2026_11_04_000007_seed_badgeforge_analytics_schedules.php');

    $migration->up();
    $migration->up();

    expect(analyticsScheduleCommands())->toBe([
        'badgeforge:analytics:aggregate',
        'badgeforge:analytics:prune',
    ]);
});

it('remove apenas os comandos da fase no rollback', function () {
    $migration = require database_path('migrations/2026_11_04_000007_seed_badgeforge_analytics_schedules.php');

    $migration->down();

    expect(analyticsScheduleCommands())->toBe([])
        ->and(DB::table('task_schedules')->where('command', 'accounts:purge-deleted')->exists())
        ->toBeTrue();
});
