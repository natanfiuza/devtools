<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona o idioma selecionado da consulta (locale) em ai_requests (ADR-056).
 *
 * Registra o locale (en, pt_BR, pt_PT, fr, es) no momento do envio para
 * auditoria, historico e isolamento de contexto. Nulo em registros anteriores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_requests', function (Blueprint $table): void {
            $table->string('locale', 10)->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ai_requests', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
