<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doacoes por Pix (F6).
 *
 * Nao se confundem com `payments`: doacao nao compra plano nem entra na cota.
 * A linha nasce pendente, guarda o QR/copia-e-cola e e confirmada pelo webhook
 * do provedor - ou fica pendente para sempre no fallback de Pix estatico, que
 * nao tem confirmacao automatica.
 *
 * O doador pode ser anonimo: `user_id`, `name` e `email` sao opcionais. A FK
 * usa `nullOnDelete` porque a conta pode ser anonimizada (ADR-028) sem levar a
 * doacao junto.
 *
 * `qr_code_base64` e `text` porque a imagem em base64 passa facil dos 255
 * caracteres de uma string comum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('locale', 5);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('status', 16)->default('pending');
            $table->string('gateway', 24);
            $table->string('gateway_ref', 64)->nullable();
            $table->text('qr_code')->nullable();
            $table->text('qr_code_base64')->nullable();
            $table->string('ticket_url', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_ref']);
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
