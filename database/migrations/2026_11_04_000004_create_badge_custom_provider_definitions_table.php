<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metricas de um provider customizado (F7, ADR-087).
 *
 * Cada definicao aponta para um JSON Pointer RFC 6901 e tem um formatador
 * fixo (`text`, `number`, `percent`). O slug e imutavel depois da criacao: a
 * URL publica ja publicada continua valida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_custom_provider_definitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('provider_id')
                ->constrained('badge_custom_providers')
                ->cascadeOnDelete();
            $table->string('slug', 32);
            $table->string('label', 80);
            $table->string('pointer', 256);
            $table->string('formatter', 16);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['provider_id', 'slug']);
            $table->index(['provider_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_custom_provider_definitions');
    }
};
