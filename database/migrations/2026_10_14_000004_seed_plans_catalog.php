<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga inicial do catalogo de planos (Q11 do plano da sessao).
 *
 * Fica numa migracao, e nao num seeder, porque o deploy roda `migrate --force`
 * e nunca `db:seed`: sem isto, o site subiria sem plano nenhum.
 *
 * `insertOrIgnore` para poder rodar de novo sem duplicar e sem sobrescrever o
 * que o admin ja tiver editado - o preco e dado do admin, nao da migracao.
 */
return new class extends Migration
{
    /**
     * @var list<array<string, mixed>>
     */
    private const PLANOS = [
        [
            'slug' => 'free',
            'name' => 'Free',
            'price_cents' => 0,
            'currency' => 'BRL',
            'interval' => null,
            'monthly_tokens' => 0,
            'max_schema_bytes' => 8192,
            'max_prompt_chars' => 500,
            'lifetime_free_uses' => 5,
            'active' => true,
            'sort' => 0,
        ],
        [
            'slug' => 'pro-monthly',
            'name' => 'Pro',
            'price_cents' => 2990,
            'currency' => 'BRL',
            'interval' => 'month',
            'monthly_tokens' => 2000000,
            'max_schema_bytes' => 65536,
            'max_prompt_chars' => 4000,
            'lifetime_free_uses' => 0,
            'active' => true,
            'sort' => 1,
        ],
        [
            'slug' => 'pro-yearly',
            'name' => 'Pro',
            'price_cents' => 29900,
            'currency' => 'BRL',
            'interval' => 'year',
            'monthly_tokens' => 2000000,
            'max_schema_bytes' => 65536,
            'max_prompt_chars' => 4000,
            'lifetime_free_uses' => 0,
            'active' => true,
            'sort' => 2,
        ],
    ];

    public function up(): void
    {
        $agora = now();

        DB::table('plans')->insertOrIgnore(array_map(
            fn (array $plano): array => [...$plano, 'created_at' => $agora, 'updated_at' => $agora],
            self::PLANOS,
        ));
    }

    /**
     * Nao ha o que desfazer: o catalogo cai junto com a tabela.
     */
    public function down(): void {}
};
