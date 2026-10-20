<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitacoes do AI SQL Builder.
 *
 * O id e ULID porque a solicitacao nasce no POST e e consultada por polling:
 * nao ha por que expor sequencia. `canonical_payload` guarda o payload
 * comprimido enquanto o job nao termina e e limpo na liquidacao; o schema e a
 * descricao nunca vao para log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('output_target_id')->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->string('dialect', 16);
            $table->string('schema_source', 16);
            $table->char('prompt_hash', 32);
            $table->unsignedSmallInteger('prompt_version');
            $table->string('prompt_template', 128);
            $table->string('model', 64);
            $table->unsignedInteger('estimated_tokens')->default(0);
            $table->unsignedInteger('reserved_tokens')->default(0);
            $table->unsignedSmallInteger('reserved_uses')->default(0);
            $table->string('quota_unit', 16);
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->string('error_code', 32)->nullable();
            $table->boolean('history_visible')->default(false);
            $table->longText('canonical_payload')->nullable();
            $table->longText('response_json')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('prompt_hash');
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'prompt_hash', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
