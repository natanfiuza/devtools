<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Textos compartilhados da ferramenta Share Text (ADR-058).
 *
 * Persiste metadados de textos criados por usuarios anonimos (expiracao em 72h)
 * e cadastrados (permanentes). Os arquivos fisicos residem em
 * storage/app/data/sharetext/{user_id|anonymous}/{ulid}.txt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_texts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('file_path', 255);
            $table->unsignedBigInteger('size_bytes');
            $table->string('extension', 16)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_texts');
    }
};
