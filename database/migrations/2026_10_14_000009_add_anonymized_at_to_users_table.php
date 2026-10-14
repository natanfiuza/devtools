<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de conta anonimizada (ADR-028). A linha nao e apagada porque
 * `payments` aponta para ela e o registro fiscal fica; o que sai e o dado
 * pessoal. A tela usa esta marca para escrever "conta removida" no idioma de
 * quem esta lendo, em vez de guardar um texto em portugues no banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
