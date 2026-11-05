<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregado diario das visualizacoes (F7, ADR-086).
 *
 * As dimensoes opcionais usam `''` como default de proposito: NULL duplicaria
 * linhas no indice unico do MySQL. `unique_visitors` e a contagem distinta de
 * `ip_hash` do dia, nunca a soma entre dias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_view_dailies', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 16);
            $table->string('subject_id', 26);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('day');
            $table->string('definition', 32)->default('');
            $table->string('country', 2)->default('');
            $table->string('referer_domain', 253)->default('');
            $table->string('format', 8)->default('');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('unique_visitors')->default(0);
            $table->unsignedBigInteger('cache_hits')->default(0);

            $table->unique(
                ['subject_type', 'subject_id', 'day', 'country', 'referer_domain', 'format', 'definition'],
                'badge_view_dailies_unique',
            );
            $table->index(['user_id', 'day']);
            $table->index(['day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_view_dailies');
    }
};
