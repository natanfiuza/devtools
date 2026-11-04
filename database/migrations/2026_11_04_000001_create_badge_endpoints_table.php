<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Endpoints JSON do BadgeForge (F5, ADR-083).
 *
 * O dono cadastra uma URL HTTPS; a imagem publica leva apenas o ULID, para
 * que a URL de origem nunca apareca em props, log, flash ou README. O nome e
 * local ao dono: dois usuarios podem usar o mesmo rotulo, mas o dono nao
 * repete o seu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_endpoints', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('url', 2048);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_endpoints');
    }
};
