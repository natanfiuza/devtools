<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguidores entre contas (F6, ADR-136).
 *
 * Como nas curtidas, a linha nao e apagada ao deixar de seguir: `followed_at`
 * nulo e o estado inativo e `notified_at` preserva o aviso unico da relacao.
 * O vinculo usa o id da conta, entao trocar o username nao perde seguidores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_follows', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('followed_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['follower_id', 'followed_id']);
            $table->index(['followed_id', 'followed_at']);
            $table->index(['follower_id', 'followed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_follows');
    }
};
