<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags de paleta por usuario (F4, ADR-103).
 *
 * A tag e uma entidade do dono, nao um texto solto na paleta: renomear
 * reflete em todas as paletas e a ordem e uma preferencia do usuario.
 * `key` normaliza caixa e espacos para impedir duplicatas (ao menos no
 * collation que diferencia caixa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_tags', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('key', 60);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_tags');
    }
};
