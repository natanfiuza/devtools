<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torna o campo de documento de identificacao (CPF / CNPJ) opcional nas
 * intencoes de assinatura Pro (retrocompativel, zero downtime).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_intents', function (Blueprint $table): void {
            $table->string('document', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_intents', function (Blueprint $table): void {
            $table->string('document', 32)->nullable(false)->change();
        });
    }
};
