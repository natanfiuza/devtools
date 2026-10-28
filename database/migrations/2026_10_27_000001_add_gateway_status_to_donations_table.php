<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status cru do pagamento no provedor (ADR-059).
 *
 * O webhook e a consulta sob demanda ja recebiam `status` e `status_detail`,
 * mas nao guardavam o detalhe - sem ele a tela so conseguia dizer "nao
 * concluida". As duas colunas sao nullable para os registros antigos e para o
 * Pix estatico, que nao tem provedor.
 *
 * Sem indice: as colunas so sao lidas depois que a linha da doacao ja foi
 * carregada pelo ULID ou pelo id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->string('gateway_status', 32)->nullable();
            $table->string('gateway_status_detail', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn(['gateway_status', 'gateway_status_detail']);
        });
    }
};
