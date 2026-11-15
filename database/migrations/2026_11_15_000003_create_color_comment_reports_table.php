<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denuncias de comentarios (F7, ADR-141).
 *
 * Uma linha por par comentario/denunciante: repetir uma denuncia pending ou
 * resolvida nao cria outra; uma denuncia dispensada e reaberta na mesma linha.
 * `resolved_by` e o admin que resolveu ou dispensou, e vira NULL no expurgo da
 * conta. O motivo e um enum fechado, sem texto livre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_comment_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('comment_id')
                ->constrained('color_palette_comments')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 16);
            $table->string('status', 12)->default('pending');
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
            $table->index(['status', 'reported_at']);
            $table->index(['comment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_comment_reports');
    }
};
