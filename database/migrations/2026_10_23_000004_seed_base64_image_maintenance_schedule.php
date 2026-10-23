<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Poda horaria de arquivos de imagem base64 expirados (ADR-055).
 *
 * Remove arquivos de imagem original e Data URI do disco fisico para conversoes
 * Free (>24h), Anonimas (>24h) e Pagas (>30 dias).
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('task_schedules')
            ->where('command', 'base64-images:prune')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('task_schedules')->insert([
            'name' => 'Poda arquivos fisicos de imagens base64 expiradas',
            'command' => 'base64-images:prune',
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
            ->where('command', 'base64-images:prune')
            ->delete();
    }
};
