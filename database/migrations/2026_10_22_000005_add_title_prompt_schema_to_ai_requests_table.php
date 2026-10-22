<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona colunas para busca e titulo nas consultas salvas no historico.
 *
 * `title`: gerado pelo modelo de IA sintetizando a intencao e saida.
 * `prompt`: conteudo da descricao da consulta para busca.
 * `schema`: definicao da estrutura enviada para busca.
 * So sao preenchidos para registros com history_visible = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_requests', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('history_visible');
            $table->text('prompt')->nullable()->after('title');
            $table->longText('schema')->nullable()->after('prompt');
        });
    }

    public function down(): void
    {
        Schema::table('ai_requests', function (Blueprint $table): void {
            $table->dropColumn(['title', 'prompt', 'schema']);
        });
    }
};
