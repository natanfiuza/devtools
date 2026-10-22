<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout avulso e preapproval em andamento (F6).
 *
 * `payments` e registro fiscal: so entra ali o que foi aprovado. Enquanto o
 * provedor nao confirma, o estado vive aqui - inclusive o `init_point` para
 * onde a pessoa foi redirecionada e o `external_reference` que volta no
 * webhook (`chk_<ulid>`).
 *
 * `gateway_ref` guarda a preapproval quando `mode = recurring`; no checkout
 * avulso guarda a preferencia criada. `approved_at` e o unico sinal de que o
 * pagamento aprovado ja abriu periodo e gravou o pagamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('mode', 16);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('status', 16)->default('pending');
            $table->string('gateway', 24)->default('mercadopago');
            $table->string('gateway_ref', 64)->nullable();
            $table->string('gateway_payment_id', 64)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->text('init_point')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
            $table->index(['gateway', 'gateway_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
