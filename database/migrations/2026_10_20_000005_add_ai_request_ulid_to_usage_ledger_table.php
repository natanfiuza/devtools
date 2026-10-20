<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga a linha do ledger a solicitacao de IA.
 *
 * `ai_request_id` e bigint e nunca foi escrito; `ai_requests.id` e ULID. Em vez
 * de alterar a coluna legada (migration destrutiva), entra `ai_request_ulid`.
 * A limpeza da coluna antiga fica para a F7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_ledger', function (Blueprint $table) {
            $table->char('ai_request_ulid', 26)->nullable();
            $table->index('ai_request_ulid');
        });
    }

    public function down(): void
    {
        Schema::table('usage_ledger', function (Blueprint $table) {
            $table->dropIndex(['ai_request_ulid']);
            $table->dropColumn('ai_request_ulid');
        });
    }
};
