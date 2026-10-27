<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Poda horaria de textos anonimos expirados da ferramenta Share Text (ADR-058).
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('task_schedules')
            ->where('command', 'share-text:prune')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('task_schedules')->insert([
            'name' => 'Poda textos compartilhados anonimos expirados',
            'command' => 'share-text:prune',
            'parameters' => null,
            'expression' => '0 * * * *',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('task_schedules')
            ->where('command', 'share-text:prune')
            ->delete();
    }
};
