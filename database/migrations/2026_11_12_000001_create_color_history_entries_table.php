<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historico de cores da conta (F5, ADR-127).
 *
 * A cor e a chave canonica do picker (`#rrggbbaa` ou `transparent`). A
 * unicidade por conta transforma a gravacao em upsert: repetir a mesma cor
 * so sobe `updated_at`, sem criar linha nova. Nao ha cota funcional; o
 * indice cobre a listagem por mais recente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_history_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('color', 16);
            $table->timestamps();

            $table->unique(['user_id', 'color']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_history_entries');
    }
};
