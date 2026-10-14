<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conta criada por login social nasce sem senha (ADR-023); a coluna passa a
 * aceitar NULL. Afrouxar a constraint e retrocompativel: o codigo antigo
 * continua lendo e escrevendo normalmente durante o deploy (ADR-014).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
