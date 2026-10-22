<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado da renovacao automatica (F6).
 *
 * `renewal_canceled_at` registra que a preapproval foi cancelada sem encerrar
 * o periodo pago: o acesso segue ate `current_period_end`. `renewal_failed_at`
 * guarda a primeira falha de cobranca do periodo, para avisar uma vez so.
 *
 * O indice `(gateway, gateway_ref)` nao e unico de proposito: uma preapproval
 * gera uma linha de assinatura por periodo, todas com a mesma referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('renewal_canceled_at')->nullable()->after('current_period_end');
            $table->timestamp('renewal_failed_at')->nullable()->after('renewal_canceled_at');

            $table->index(['gateway', 'gateway_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['gateway', 'gateway_ref']);
            $table->dropColumn(['renewal_canceled_at', 'renewal_failed_at']);
        });
    }
};
