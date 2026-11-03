<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logos personalizados do BadgeForge (F3, ADR-078).
 *
 * Guarda apenas o resultado normalizado do SVG: o viewBox e a lista de paths.
 * O arquivo enviado nao e persistido, entao nao existe upload servido como
 * arquivo estatico. O id ULID e a parte publica da referencia (`u_<ULID>`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_custom_logos', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('view_box', 64);
            $table->json('paths');
            $table->unsignedSmallInteger('path_count');
            $table->unsignedInteger('size_bytes');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_custom_logos');
    }
};
