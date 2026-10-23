<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historico de conversoes da ferramenta Base64 Image (ADR-055).
 *
 * Persiste metadados de imagens convertidas por usuarios cadastrados (Free e
 * Pagos). O identificador e ULID. Arquivos fisicos no disco sao removidos
 * apos 24 horas (Free) ou 30 dias (Pagos), marcando `file_deleted_at`, enquanto
 * o registro no banco de dados e mantido para consulta historica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base64_image_conversions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('filename', 255);
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedBigInteger('base64_size_bytes');
            $table->boolean('is_paid_plan')->default(false);
            $table->string('disk_path_original', 255)->nullable();
            $table->string('disk_path_converted', 255)->nullable();
            $table->timestamp('file_deleted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['file_deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base64_image_conversions');
    }
};
