<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencia de historico por conta (F7).
 *
 * Nasce desligada; quem ja tem consultas visiveis ganha a preferencia ligada
 * para nao perder o que ja aparecia na tela. A coluna e aditiva: a versao
 * anterior do codigo simplesmente a ignora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('history_enabled')->default(false)->after('appearance');
        });

        DB::table('users')
            ->whereExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('ai_requests')
                    ->whereColumn('ai_requests.user_id', 'users.id')
                    ->where('ai_requests.history_visible', true);
            })
            ->update(['history_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('history_enabled');
        });
    }
};
