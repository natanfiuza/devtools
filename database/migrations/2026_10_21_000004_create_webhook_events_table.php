<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificacoes cruas do gateway (F6).
 *
 * O corpo completo fica guardado para auditoria e reprocessamento; o unique
 * `(gateway, event_id)` e a primeira linha de defesa contra replay. A poda
 * roda por comando (`webhooks:prune`) e nunca apaga pagamentos, assinaturas ou
 * doacoes - so o log de eventos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 24);
            $table->string('event_id', 64);
            $table->string('topic', 48)->nullable();
            $table->string('action', 64)->nullable();
            $table->string('resource_id', 64)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('payload');
            $table->string('error', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
