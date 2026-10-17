<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Novos precos do catalogo Pro (R$ 8,90 mensal / R$ 89,00 anual), reserva de
 * `@username` na conta e tabela de intencoes de assinatura (ADR-033).
 *
 * Retrocompativel (zero downtime): apenas adiciona coluna `nullable` em `users`,
 * cria tabela nova `subscription_intents` e atualiza os centavos dos planos Pro.
 */
return new class extends Migration
{
    public function up(): void
    {
        $agora = now();

        DB::table('plans')
            ->where('slug', 'pro-monthly')
            ->update(['price_cents' => 890, 'updated_at' => $agora]);

        DB::table('plans')
            ->where('slug', 'pro-yearly')
            ->update(['price_cents' => 8900, 'updated_at' => $agora]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->unique()->after('name');
        });

        Schema::create('subscription_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('username', 30);
            $table->string('phone', 32);
            $table->string('document', 32);
            $table->string('payment_method', 16)->default('pix');
            $table->string('notes', 500)->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_intents');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
