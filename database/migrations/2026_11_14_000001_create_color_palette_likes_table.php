<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curtidas de paletas publicas (F6, ADR-135).
 *
 * O ator e a conta (`user_id`) ou o visitante (`anonymous_id`), nunca os dois.
 * A linha e permanente: `liked_at` nulo significa descurtida e `notified_at`
 * guarda a primeira notificacao, para que descurtir e curtir de novo nao avise
 * o dono outra vez. O identificador anonimo e um valor aleatorio, nunca o IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_palette_likes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('color_palette_id')
                ->constrained('color_palettes')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('anonymous_id', 64)->nullable();
            $table->timestamp('liked_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            // O NULL e distinto em indice unico no MySQL e no SQLite: os dois
            // indices separados impedem duplicata por conta e por anonimo sem
            // que uma linha anonima colida com uma linha de conta.
            $table->unique(['color_palette_id', 'user_id']);
            $table->unique(['color_palette_id', 'anonymous_id']);
            $table->index(['color_palette_id', 'liked_at']);
            $table->index(['user_id', 'liked_at']);
            $table->index(['anonymous_id', 'liked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_palette_likes');
    }
};
