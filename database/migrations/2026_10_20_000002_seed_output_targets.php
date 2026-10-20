<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga inicial dos nove alvos de saida (SPEC secao 6).
 *
 * Mesmo motivo da carga dos planos: o deploy roda `migrate --force` e nunca
 * `db:seed`. `insertOrIgnore` deixa rodar de novo sem duplicar e sem
 * sobrescrever o que o admin tiver editado.
 */
return new class extends Migration
{
    /**
     * @var list<array<string, mixed>>
     */
    private const TARGETS = [
        [
            'slug' => 'sql',
            'label' => 'SQL',
            'language' => 'sql',
            'supported_dialects' => ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
            'prompt_template' => 'sql.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 10,
        ],
        [
            'slug' => 'laravel_query_builder',
            'label' => 'Laravel Query Builder',
            'language' => 'php',
            'supported_dialects' => ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
            'prompt_template' => 'laravel_query_builder.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 20,
        ],
        [
            'slug' => 'eloquent',
            'label' => 'Eloquent',
            'language' => 'php',
            'supported_dialects' => ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
            'prompt_template' => 'eloquent.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 30,
        ],
        [
            'slug' => 'sqlmodel',
            'label' => 'SQLModel',
            'language' => 'python',
            'supported_dialects' => ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
            'prompt_template' => 'sqlmodel.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 40,
        ],
        [
            'slug' => 'sqlalchemy',
            'label' => 'SQLAlchemy',
            'language' => 'python',
            'supported_dialects' => ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
            'prompt_template' => 'sqlalchemy.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 50,
        ],
        [
            'slug' => 'django_orm',
            'label' => 'Django ORM',
            'language' => 'python',
            'supported_dialects' => ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
            'prompt_template' => 'django_orm.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 60,
        ],
        [
            'slug' => 'prisma',
            'label' => 'Prisma',
            'language' => 'typescript',
            'supported_dialects' => ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
            'prompt_template' => 'prisma.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 70,
        ],
        [
            'slug' => 'drizzle',
            'label' => 'Drizzle ORM',
            'language' => 'typescript',
            'supported_dialects' => ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
            'prompt_template' => 'drizzle.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 80,
        ],
        [
            'slug' => 'typeorm',
            'label' => 'TypeORM',
            'language' => 'typescript',
            'supported_dialects' => ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
            'prompt_template' => 'typeorm.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 90,
        ],
    ];

    public function up(): void
    {
        $agora = now();

        DB::table('output_targets')->insertOrIgnore(array_map(
            fn (array $target): array => [
                ...$target,
                'supported_dialects' => json_encode($target['supported_dialects'], JSON_THROW_ON_ERROR),
                'created_at' => $agora,
                'updated_at' => $agora,
            ],
            self::TARGETS,
        ));
    }

    /**
     * Nao ha o que desfazer: o catalogo cai junto com a tabela.
     */
    public function down(): void {}
};
