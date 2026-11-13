<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paletas publicas favoritadas pela conta (F5, ADR-128).
 *
 * So guarda o vinculo: o retrato da paleta continua sendo o da origem e a
 * listagem so mostra o que ainda e publico e ativo. A unicidade impede
 * favoritar a mesma paleta duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_palette_favorites', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('color_palette_id')
                ->constrained('color_palettes')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'color_palette_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_palette_favorites');
    }
};
