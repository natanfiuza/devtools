<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinaturas (ADR-027).
 *
 * A vigencia e resolvida na leitura (`current_period_end`), nao por rotina
 * agendada: o deploy nao roda `schedule:run`. Por isso `expired` nao e um
 * estado gravado - uma assinatura ativa com periodo vencido simplesmente deixa
 * de ser corrente.
 *
 * `gateway` ja existe para a F6; hoje so recebe `manual`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 24)->default('manual');
            $table->string('gateway_ref', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
