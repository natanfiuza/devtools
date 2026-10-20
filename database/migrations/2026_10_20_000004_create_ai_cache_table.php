<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache de respostas da IA por `prompt_hash` (SPEC secao 6).
 *
 * O hash cobre schema, descricao, dialeto, alvo, modelo e versao do prompt -
 * dois pedidos identicos caem na mesma linha. O payload canonico fica
 * comprimido para auditoria e para o job reidratar a solicitacao; a resposta
 * so entra depois de passar pelo contrato e pelo gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_cache', function (Blueprint $table) {
            $table->id();
            $table->char('prompt_hash', 32)->unique();
            $table->longText('canonical_payload');
            $table->longText('response_json');
            $table->string('model', 64);
            $table->unsignedSmallInteger('prompt_version');
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at');

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cache');
    }
};
