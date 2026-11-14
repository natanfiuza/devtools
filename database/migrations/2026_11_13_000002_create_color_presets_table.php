<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presets do Color Palette Generator (F5, ADR-093).
 *
 * O preset e uma configuracao de geracao salva pela conta, nao uma paleta:
 * guarda so a entrada (nome + cor base) e nao entra na cota, no catalogo
 * publico nem no contador de uso. So cria tabela; nada existente e alterado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_presets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('base', 9);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_presets');
    }
};
