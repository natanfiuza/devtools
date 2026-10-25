<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ativa o historico de consultas por padrao (`users.history_enabled = true`)
 * e torna visiveis as consultas ja geradas no AI SQL Builder.
 *
 * A coluna `history_enabled` passa a ser opt-out (default `true`): novas contas
 * e contas existentes gravam as consultas geradas no AI SQL Builder, e o
 * backfill preenche `history_visible = true` e recupera `title` do
 * `response_json` quando disponivel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('history_enabled')->default(true)->change();
        });

        DB::table('users')->update(['history_enabled' => true]);

        DB::table('ai_requests')->update(['history_visible' => true]);

        DB::table('ai_requests')
            ->whereNull('title')
            ->whereNotNull('response_json')
            ->orderBy('id')
            ->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->response_json, true);

                    if (is_array($decoded) && isset($decoded['title']) && is_string($decoded['title']) && trim($decoded['title']) !== '') {
                        DB::table('ai_requests')
                            ->where('id', $row->id)
                            ->update(['title' => trim($decoded['title'])]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('history_enabled')->default(false)->change();
        });
    }
};
