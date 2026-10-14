<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista da newsletter com double opt-in. Sem IP: a confirmacao por e-mail e o
 * proprio registro de consentimento, e guardar IP seria dado a mais (ADR-024).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('token', 64)->unique();
            $table->string('locale', 5);
            $table->string('source', 16);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
