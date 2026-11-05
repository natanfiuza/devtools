<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Definicoes de metrica dos providers do BadgeForge (F4, ADR-080).
 *
 * Cada linha descreve um badge possivel: o path que o usuario digita, a URL
 * resolvida e a chave da metrica. `parser_class` fica inerte; quem escolhe o
 * cliente e `client_key` (registry) e quem escolhe o valor e `extractor_key`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_provider_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('badge_providers')->cascadeOnDelete();
            $table->string('slug', 40);
            $table->string('name', 80);
            $table->string('label', 40);
            $table->string('client_key', 40);
            $table->string('extractor_key', 40);
            $table->string('path_pattern', 120);
            $table->json('required_params');
            $table->json('optional_params')->nullable();
            $table->string('url_template', 255);
            $table->string('response_type', 20)->default('json');
            $table->string('parser_class', 255)->nullable();
            $table->boolean('supports_private')->default(false);
            $table->string('example_path', 255);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['provider_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_provider_definitions');
    }
};
