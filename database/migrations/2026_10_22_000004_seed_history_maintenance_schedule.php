<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Poda diaria do historico de consultas (F7).
 *
 * A linha e aditiva e idempotente por `command`; sem parametros, o comando usa
 * `ai.history.retention_days` (90 por padrao).
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('task_schedules')
            ->where('command', 'history:prune')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('task_schedules')->insert([
            'name' => 'Poda o historico de consultas antigas',
            'command' => 'history:prune',
            'parameters' => null,
            'expression' => '45 3 * * *',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('task_schedules')
            ->where('command', 'history:prune')
            ->delete();
    }
};
