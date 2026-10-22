<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rotinas de manutencao do checkout e dos webhooks (F6).
 *
 * As linhas de `task_schedules` sao aditivas e idempotentes: reaplicar a
 * migracao nao duplica. A expiracao e uma varredura de conforto - a leitura
 * tambem expira de forma preguicosa - e a poda so remove eventos de webhook
 * antigos, nunca registros fiscais.
 */
return new class extends Migration
{
    /**
     * @var list<array{name: string, command: string, parameters: array<string, int>, expression: string}>
     */
    private array $schedules = [
        [
            'name' => 'Expira checkouts e doacoes pendentes',
            'command' => 'payments:expire',
            'parameters' => [],
            'expression' => '*/5 * * * *',
        ],
        [
            'name' => 'Poda eventos de webhook (30 dias)',
            'command' => 'webhooks:prune',
            'parameters' => ['--days' => 30],
            'expression' => '30 3 * * *',
        ],
    ];

    public function up(): void
    {
        foreach ($this->schedules as $schedule) {
            $exists = DB::table('task_schedules')
                ->where('command', $schedule['command'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('task_schedules')->insert([
                'name' => $schedule['name'],
                'command' => $schedule['command'],
                'parameters' => $schedule['parameters'] === []
                    ? null
                    : json_encode($schedule['parameters']),
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
            ->whereIn('command', array_column($this->schedules, 'command'))
            ->delete();
    }
};
