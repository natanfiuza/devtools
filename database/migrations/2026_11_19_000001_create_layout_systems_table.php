<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistemas de layout persistidos do Layout Harmonizer (F4, ADR-168).
 *
 * `state` e o retrato efetivo do workspace (snapshot da F3 + entradas cruas +
 * secundaria manual); reabrir nao recalcula nada. As colunas de versao e a
 * contagem de tokens sao derivadas do snapshot validado no servidor. `slug` so
 * nasce na primeira publicacao e e unico global; ao arquivar ele migra para
 * `archived_slug` (tambem unico) e a URL publica deixa de existir (ADR-171).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_systems', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('duplicated_from_id')->nullable()
                ->constrained('layout_systems')->nullOnDelete();
            $table->foreignUlid('source_palette_id')->nullable()
                ->constrained('color_palettes')->nullOnDelete();

            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->string('visibility', 16)->default('private');
            $table->json('state');
            // Credito congelado da paleta de origem (ADR-172).
            $table->json('source')->nullable();

            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedSmallInteger('algorithm_version')->default(1);
            $table->unsignedSmallInteger('suggestions_version')->default(1);
            $table->unsignedSmallInteger('snapshot_version')->default(1);
            $table->unsignedSmallInteger('tokens_count')->default(66);
            $table->unsignedInteger('duplications_count')->default(0);

            $table->string('slug', 60)->nullable()->unique();
            $table->string('archived_slug', 60)->nullable()->unique();
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'name']);
            $table->index(['visibility', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_systems');
    }
};
