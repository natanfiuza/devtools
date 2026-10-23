<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limite de upload de imagem por plano (ADR-055).
 *
 * Limite de 2 MB para plano gratuito e 25 MB para planos pagos, permitindo
 * reconfiguracao pelo painel administrativo sem necessidade de novo deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('max_image_upload_bytes')
                ->default(2097152)
                ->after('max_prompt_chars');
        });

        DB::table('plans')
            ->whereIn('slug', ['pro-monthly', 'pro-yearly'])
            ->update(['max_image_upload_bytes' => 26214400]);

        DB::table('plans')
            ->where('slug', 'free')
            ->update(['max_image_upload_bytes' => 2097152]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_image_upload_bytes');
        });
    }
};
