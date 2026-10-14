<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vinculo entre conta e provedor social (Google, GitHub). Tabela nova: pode
 * entrar em producao sem downtime (ADR-014, ADR-023).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_id', 255);
            $table->timestamps();

            // Um provedor so pode apontar para uma conta, e a mesma conta pode
            // ter Google e GitHub (por isso a tabela, e nao colunas em users).
            $table->unique(['provider', 'provider_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
