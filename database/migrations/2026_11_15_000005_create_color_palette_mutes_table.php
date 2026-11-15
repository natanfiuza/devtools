<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paletas silenciadas pelo dono (F7, ADR-142).
 *
 * So o dono silencia a propria paleta. O mute corta curtidas, comentarios e
 * respostas daquela paleta em app e e-mail, avaliado no envio; follows nao sao
 * afetados. A linha e um vinculo da conta com a paleta, sem copia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_palette_mutes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('color_palette_id')
                ->constrained('color_palettes')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['color_palette_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_palette_mutes');
    }
};
