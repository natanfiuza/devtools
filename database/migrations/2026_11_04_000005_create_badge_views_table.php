<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visualizacoes brutas de imagens com dono (F7, ADR-086).
 *
 * O sujeito e polimorfico (`personal`, `endpoint`, `custom`) e guarda o ULID
 * como string opaca. IP e User-Agent nunca ficam em claro: so os HMAC
 * calculados com `APP_KEY` entram; o UA cru e opcional e truncado. A retencao
 * e controlada pelo comando de poda, nao pelo banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_views', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 16);
            $table->string('subject_id', 26);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('definition', 32)->nullable();
            $table->char('ip_hash', 64);
            $table->char('user_agent_hash', 64);
            $table->string('user_agent', 512)->nullable();
            $table->string('referer_domain', 253)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('format', 8);
            $table->string('style', 32)->nullable();
            $table->boolean('cache_hit')->nullable();
            $table->timestamp('viewed_at');

            $table->index(['subject_type', 'subject_id', 'viewed_at']);
            $table->index(['user_id', 'viewed_at']);
            $table->index(['viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_views');
    }
};
