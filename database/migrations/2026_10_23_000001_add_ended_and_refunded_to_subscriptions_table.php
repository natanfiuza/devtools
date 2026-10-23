<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cota acumulativa (ADR-054): o direito de tokens e calculado na leitura a
 * partir das linhas de `subscriptions`, e para isso a linha precisa dizer
 * quando foi encerrada e se o encerramento foi um estorno.
 *
 * - `ended_at`: gravado em todo cancelamento (troca de plano, downgrade,
 *   estorno, anonimizacao). Os meses iniciados antes dele continuam valendo.
 * - `refunded_at`: gravado so no estorno. A linha estornada nao credita nada.
 *
 * Colunas nulas e sem backfill: retrocompativel com o codigo que ainda nao as
 * conhece (deploy sem downtime). Linha `canceled` antiga sem `ended_at` vale 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable()->after('renewal_failed_at');
            $table->timestamp('refunded_at')->nullable()->after('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['ended_at', 'refunded_at']);
        });
    }
};
