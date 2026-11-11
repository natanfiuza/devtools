<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vinculo entre paletas e tags (F4, ADR-103).
 *
 * Sem posicao no pivo: a ordem exibida e a global da tag no usuario, e nao
 * uma ordem por paleta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_palette_color_tag', function (Blueprint $table): void {
            $table->foreignUlid('color_palette_id')->constrained('color_palettes')->cascadeOnDelete();
            $table->foreignUlid('color_tag_id')->constrained('color_tags')->cascadeOnDelete();

            $table->primary(['color_palette_id', 'color_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_palette_color_tag');
    }
};
