<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara `ai_cache` para hospedar mais de um uso (F7, ADR-140).
 *
 * A tabela nasceu exclusiva do AI SQL Builder, com expiracao obrigatoria. A
 * moderacao de comentarios compartilha a mesma tabela, mas precisa de linhas
 * permanentes e de isolamento explicito entre os usos: `purpose` separa as
 * linhas e `expires_at` nullable passa a significar "sem expiracao".
 *
 * A coluna nova tem default `ai`, entao as linhas antigas continuam validas
 * sem backfill. O rollback preenche as linhas permanentes com `now()` antes de
 * voltar a coluna para NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_cache', function (Blueprint $table): void {
            $table->string('purpose', 32)->default('ai')->after('prompt_hash')->index();
            $table->timestamp('expires_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('ai_cache')->whereNull('expires_at')->update(['expires_at' => now()]);

        Schema::table('ai_cache', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable(false)->change();
            $table->dropIndex(['purpose']);
            $table->dropColumn('purpose');
        });
    }
};
