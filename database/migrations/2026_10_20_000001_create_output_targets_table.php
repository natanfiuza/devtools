<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alvos de saida do AI SQL Builder.
 *
 * O alvo e dado, nao codigo: o admin pode desligar um alvo sem deploy e o
 * prompt de cada um e um arquivo versionado (`resources/prompts`) referenciado
 * por `prompt_template`. `supported_dialects` guarda os dialetos aceitos pelo
 * alvo; `plan_required` fica nulo enquanto todo mundo pode usar (F5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('output_targets', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('label', 64);
            $table->string('language', 16);
            $table->json('supported_dialects');
            $table->string('prompt_template', 128);
            $table->boolean('enabled')->default(true);
            $table->foreignId('plan_required')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['enabled', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('output_targets');
    }
};
