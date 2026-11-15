<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencias de e-mail das notificacoes Colors (F7, ADR-142).
 *
 * O canal `database` e sempre ligado; estas quatro colunas decidem apenas o
 * e-mail. Comentario e resposta nascem ligados, curtida e novo seguidor
 * desligados, como decidido na F7. A conta sem linha usa os defaults do model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_notification_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->boolean('email_on_like')->default(false);
            $table->boolean('email_on_follow')->default(false);
            $table->boolean('email_on_comment')->default(true);
            $table->boolean('email_on_reply')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_notification_preferences');
    }
};
