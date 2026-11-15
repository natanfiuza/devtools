<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comentarios de paletas publicas (F7, ADR-111 e ADR-139).
 *
 * `parent_id` so aponta para uma raiz: o servidor recusa responder a uma
 * resposta, entao a thread nunca passa de dois niveis. `body` e o texto atual;
 * `approved_body` guarda a ultima versao aprovada, exibida quando uma edicao e
 * rejeitada. `revision` invalida resultados de moderacao de revisoes antigas.
 * `user_id` nullable com `nullOnDelete` preserva a thread no expurgo definitivo
 * da conta; a autoria vira "conta removida".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_palette_comments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('color_palette_id')
                ->constrained('color_palettes')
                ->cascadeOnDelete();
            $table->foreignUlid('parent_id')
                ->nullable()
                ->constrained('color_palette_comments')
                ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->text('approved_body')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('locale', 5);
            $table->unsignedInteger('revision')->default(1);
            $table->string('moderation_reason', 32)->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['color_palette_id', 'created_at']);
            $table->index(['color_palette_id', 'status']);
            $table->index(['parent_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_palette_comments');
    }
};
