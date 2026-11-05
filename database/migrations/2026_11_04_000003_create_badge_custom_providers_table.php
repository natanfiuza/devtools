<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Providers JSON customizados (F7, ADR-087).
 *
 * O provider pertence ao dono e tem URL publica opaca por ULID; o template de
 * origem fica no servidor e nunca viaja nas props do dono. `metrics_version`
 * entra no namespace de cache para que editar uma metrica invalide o valor
 * anterior sem varrer o store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_custom_providers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('url_template', 2048);
            $table->json('path_params');
            $table->string('example_path', 512);
            $table->unsignedInteger('metrics_version')->default(1);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_custom_providers');
    }
};
