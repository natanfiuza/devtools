<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presets do BadgeForge (F3, ADR-079).
 *
 * O preset e um snapshot do formulario, nao um badge publicado: nao tem URL
 * publica nem contador. O logo referenciado vive dentro do `payload`; se ele
 * sumir, a leitura marca o preset como orfao e a UI cai para "sem logo".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_presets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_presets');
    }
};
