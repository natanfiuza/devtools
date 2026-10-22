<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Como o gateway cobrou (cartao, pix, boleto) e a referencia dele (F6).
 *
 * `method` continua sendo a forma de pagamento; `gateway_method` guarda o
 * `payment_method_id` devolvido pela Mercado Pago, que pode ser mais especifico
 * do que o enum local. O unique `(gateway, gateway_ref)` e a garantia fisica de
 * que o mesmo pagamento do provedor nao entra duas vezes: os registros manuais
 * tem `gateway_ref` nulo e o MySQL aceita varios nulos no indice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_method', 32)->nullable()->after('method');

            $table->unique(['gateway', 'gateway_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway', 'gateway_ref']);
            $table->dropColumn('gateway_method');
        });
    }
};
