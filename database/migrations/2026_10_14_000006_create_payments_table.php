<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos registrados a mao pelo admin (ADR-027) e, na F6, pelo gateway.
 *
 * A cobranca e retida por obrigacao fiscal mesmo depois de a conta ser
 * anonimizada (ADR-028) - por isso a FK e `restrictOnDelete`: o banco recusa
 * apagar a conta de quem tem historico de pagamento, em vez de perder o
 * registro em silencio.
 *
 * O valor nunca e negativo: um estorno e `status = refunded`, nao um lancamento
 * de sinal contrario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('method', 16)->default('pix');
            $table->string('status', 16)->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('gateway', 24)->default('manual');
            $table->string('gateway_ref', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'paid_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
