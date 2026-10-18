<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('command');
            $table->json('parameters')->nullable();
            $table->string('expression', 64)->default('dailyAt:03:00');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        // Semente inicial da rotina de expurgo de contas em soft delete
        DB::table('task_schedules')->insert([
            'name' => 'Expurgo de contas excluídas (30 dias)',
            'command' => 'accounts:purge-deleted',
            'parameters' => json_encode(['--days' => 30]),
            'expression' => 'dailyAt:03:00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_schedules');
    }
};
