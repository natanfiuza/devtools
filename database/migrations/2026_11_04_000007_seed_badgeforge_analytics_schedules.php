<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agendamentos do analytics do BadgeForge (F7, ADR-086).
 *
 * A agregacao roda de hora em hora e reconstroi os ultimos dias; a poda roda
 * uma vez por dia, depois da agregacao. `insertOrIgnore` permite reaplicar o
 * deploy sem duplicar linha e `down()` remove apenas os comandos da fase.
 */
return new class extends Migration
{
    /**
     * @var list<array{name: string, command: string, expression: string}>
     */
    private const SCHEDULES = [
        [
            'name' => 'BadgeForge analytics aggregate',
            'command' => 'badgeforge:analytics:aggregate',
            'expression' => '13 * * * *',
        ],
        [
            'name' => 'BadgeForge analytics prune',
            'command' => 'badgeforge:analytics:prune',
            'expression' => '17 4 * * *',
        ],
    ];

    public function up(): void
    {
        foreach (self::SCHEDULES as $schedule) {
            $exists = DB::table('task_schedules')
                ->where('command', $schedule['command'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('task_schedules')->insert([
                'name' => $schedule['name'],
                'command' => $schedule['command'],
                'parameters' => json_encode([], JSON_THROW_ON_ERROR),
                'expression' => $schedule['expression'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('task_schedules')
            ->whereIn('command', array_column(self::SCHEDULES, 'command'))
            ->delete();
    }
};
