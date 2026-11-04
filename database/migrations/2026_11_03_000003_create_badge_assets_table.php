<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Badges pessoais do BadgeForge (F4, ADR-081).
 *
 * A URL publica carrega username + ULID; nao ha coluna `slug` porque nao ha
 * alias publico. O dono e obrigatorio: badge pessoal sempre pertence a uma
 * conta (o SPEC previa nullable para uma badge anonima que nao existe).
 *
 * `credential` guarda o PAT cifrado pelo cast `encrypted` do model; a
 * impressao digital HMAC (nunca o token) entra na chave de cache. Os paths
 * gravados sao relativos a base de `personal_badges.path`, para sobreviver a
 * mudanca de diretorio entre deploys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->boolean('private')->default(false);
            $table->string('provider_slug', 40)->nullable();
            $table->string('definition_slug', 40)->nullable();
            $table->json('params')->nullable();
            $table->json('form')->nullable();
            $table->text('credential')->nullable();
            $table->string('credential_username', 80)->nullable();
            $table->string('credential_fingerprint', 64)->nullable();
            $table->timestamp('credential_set_at')->nullable();
            $table->string('svg_path', 255)->nullable();
            $table->string('png_path', 255)->nullable();
            $table->string('etag', 64)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_assets');
    }
};
