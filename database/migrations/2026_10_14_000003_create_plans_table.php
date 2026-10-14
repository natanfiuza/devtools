<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo de planos (ADR-027).
 *
 * Os precos e os limites vivem na tabela, nunca no codigo (regra do
 * CLAUDE.md): o admin edita pela tela de planos. A carga inicial e a migracao
 * seguinte - DDL e dado de referencia sao coisas diferentes, e so o dado
 * precisa saber rodar de novo.
 *
 * `interval` nulo e o plano sem periodo (o Free, que nao expira).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 64);
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->string('interval', 8)->nullable();
            $table->unsignedBigInteger('monthly_tokens')->default(0);
            $table->unsignedInteger('max_schema_bytes')->default(0);
            $table->unsignedInteger('max_prompt_chars')->default(0);
            $table->unsignedInteger('lifetime_free_uses')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
