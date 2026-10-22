<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga uma consulta da API publica ao token que a criou (F7).
 *
 * Nulo nas consultas da web. O indice sustenta a listagem escopada por token.
 * Sem chave estrangeira para `personal_access_tokens` no banco: o SQLite dos
 * testes nao adiciona FK em tabela existente, e a regra de escopo e aplicada
 * na consulta (o token revogado deixa de enxergar as proprias consultas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_requests', function (Blueprint $table): void {
            $table->foreignId('api_token_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ai_requests', function (Blueprint $table): void {
            $table->dropColumn('api_token_id');
        });
    }
};
