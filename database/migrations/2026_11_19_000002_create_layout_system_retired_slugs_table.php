<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slugs de sistemas de layout aposentados (F4, ADR-171).
 *
 * Excluir um sistema aposenta o slug para sempre: o link antigo responde 404
 * e o mesmo nome gera outro slug. A tabela guarda apenas o valor retirado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_system_retired_slugs', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_system_retired_slugs');
    }
};
