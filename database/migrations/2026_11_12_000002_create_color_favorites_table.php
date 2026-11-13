<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cores favoritas da conta (F5, ADR-127).
 *
 * Substitui o armazenamento local do picker para quem esta logado e
 * compartilha a lista com o Color Palette Generator. A unicidade impede
 * favoritar a mesma cor duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_favorites', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('color', 16);
            $table->timestamps();

            $table->unique(['user_id', 'color']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_favorites');
    }
};
