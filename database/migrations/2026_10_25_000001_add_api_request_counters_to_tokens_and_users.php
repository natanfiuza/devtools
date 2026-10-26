<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contadores de requisicoes HTTP e consultas SQL via API publica (ADR-057).
 *
 * `personal_access_tokens` guarda o uso de cada token ativo, enquanto `users`
 * acumula o historico vitalicio da conta na API para que a revogacao de um
 * token ou a poda de 90 dias de `ai_requests` nao diminuam os totais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('requests_count')->default(0)->after('abilities');
            $table->unsignedBigInteger('sql_queries_count')->default(0)->after('requests_count');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('api_requests_count')->default(0)->after('history_enabled');
            $table->unsignedBigInteger('api_sql_queries_count')->default(0)->after('api_requests_count');
        });

        // Backfill a partir das consultas existentes criadas por tokens de API.
        if (Schema::hasTable('ai_requests')) {
            $tokenCounts = DB::table('ai_requests')
                ->whereNotNull('api_token_id')
                ->selectRaw('api_token_id, COUNT(*) as total')
                ->groupBy('api_token_id')
                ->get();

            foreach ($tokenCounts as $row) {
                $count = (int) $row->total;

                if ($count > 0 && $row->api_token_id !== null) {
                    DB::table('personal_access_tokens')
                        ->where('id', (int) $row->api_token_id)
                        ->update([
                            'requests_count' => $count,
                            'sql_queries_count' => $count,
                        ]);
                }
            }

            $userCounts = DB::table('ai_requests')
                ->whereNotNull('api_token_id')
                ->selectRaw('user_id, COUNT(*) as total')
                ->groupBy('user_id')
                ->get();

            foreach ($userCounts as $row) {
                $count = (int) $row->total;

                if ($count > 0 && $row->user_id !== null) {
                    DB::table('users')
                        ->where('id', (int) $row->user_id)
                        ->update([
                            'api_requests_count' => $count,
                            'api_sql_queries_count' => $count,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['api_requests_count', 'api_sql_queries_count']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn(['requests_count', 'sql_queries_count']);
        });
    }
};
