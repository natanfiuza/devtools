<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paletas persistidas do Color Palette Generator (F4, ADR-123).
 *
 * As cores efetivas ficam em `colors` e os papeis semanticos em `roles`;
 * reabrir a paleta nao recalcula nada. `slug` e unico global e imutavel
 * depois de publicado; ao arquivar ele migra para `archived_slug` e a URL
 * publica deixa de existir (ADR-119).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_palettes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('duplicated_from_id')->nullable()
                ->constrained('color_palettes')->nullOnDelete();

            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->string('visibility', 10)->default('private');
            $table->string('kind', 16);
            $table->unsignedSmallInteger('algorithm_version');

            $table->json('colors');
            $table->json('roles');
            $table->json('source')->nullable();

            $table->unsignedSmallInteger('colors_count');
            $table->unsignedInteger('duplications_count')->default(0);

            $table->string('slug', 60)->nullable()->unique();
            $table->string('archived_slug', 60)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('og_version')->nullable();

            $table->timestamps();

            $table->index('archived_slug');
            $table->index('duplicated_from_id');
            $table->index(['user_id', 'archived_at']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'colors_count']);
            $table->index(['user_id', 'duplications_count']);
            $table->index(['visibility', 'archived_at', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_palettes');
    }
};
