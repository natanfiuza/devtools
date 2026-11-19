<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vinculo entre sistemas de layout e tags (F4, ADR-168).
 *
 * Sem posicao no pivo: a ordem exibida e a global da tag no usuario, como no
 * vinculo das paletas (ADR-103).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_tag_layout_system', function (Blueprint $table): void {
            $table->foreignUlid('layout_system_id')->constrained('layout_systems')->cascadeOnDelete();
            $table->foreignUlid('color_tag_id')->constrained('color_tags')->cascadeOnDelete();

            $table->primary(['layout_system_id', 'color_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_tag_layout_system');
    }
};
