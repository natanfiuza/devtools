<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Providers externos do BadgeForge (F4, ADR-080).
 *
 * O metadado mora no banco para o admin poder desligar um provider, mas o
 * codigo nunca instancia uma classe vinda daqui: `client_key` e mapeado pelo
 * registry para classes conhecidas. `requires_auth` diz que o item *privado*
 * exige credencial; o acesso publico continua anonimo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->string('category', 40)->default('vcs');
            $table->boolean('enabled')->default(true);
            $table->boolean('requires_auth')->default(false);
            $table->string('auth_type', 20)->nullable();
            $table->string('base_url', 255)->nullable();
            $table->unsignedInteger('cache_ttl')->nullable();
            $table->string('documentation_url', 255)->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_providers');
    }
};
