<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Livro-razao do consumo (a Cota do glossario).
 *
 * Toda a contabilidade e derivada daqui: a cota nao tem coluna de saldo, porque
 * saldo e o que se perde quando duas escritas concorrem. `ai_request_id` nasce
 * sem FK de proposito - a tabela `ai_requests` so existe na F5, e esta migracao
 * precisa rodar antes dela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ai_request_id')->nullable();
            $table->string('type', 16);
            $table->unsignedBigInteger('tokens')->default(0);
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_ledger');
    }
};
