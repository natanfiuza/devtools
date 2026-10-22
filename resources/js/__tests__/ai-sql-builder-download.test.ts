import { describe, expect, it } from 'vitest';
import { formatDownloadContent } from '@/tools/ai-sql-builder/formatDownload';

describe('formatDownloadContent', () => {
    const labels = {
        explanation: 'Como a consulta foi construída',
        tip: 'Dica de boas práticas',
        assumptions: 'Premissas',
        warnings: 'Avisos',
        tables: 'Tabelas usadas',
    };

    it('retorna o codigo intacto quando nao ha premissas, avisos ou tabelas', () => {
        const code = 'SELECT * FROM users;';
        const result = formatDownloadContent({
            target: 'sql',
            code,
            assumptions: [],
            warnings: [],
            tablesUsed: [],
            labels,
        });

        expect(result).toBe(code);
    });

    it('formata cabecalho em bloco C-style para SQL com todas as secoes incluindo explicacao e dica', () => {
        const code = 'SELECT id, name FROM users;';
        const result = formatDownloadContent({
            target: 'sql',
            code,
            explanation: 'Consulta simples com filtro por status.',
            tip: 'Verifique os índices.',
            assumptions: ['Tabela users possui id e name', 'Ativos apenas'],
            warnings: ['Sem paginacao'],
            tablesUsed: ['users'],
            labels,
        });

        expect(result).toBe(
            `/*\n` +
                ` * Como a consulta foi construída:\n` +
                ` * Consulta simples com filtro por status.\n` +
                ` *\n` +
                ` * Dica de boas práticas:\n` +
                ` * Verifique os índices.\n` +
                ` *\n` +
                ` * Premissas:\n` +
                ` * - Tabela users possui id e name\n` +
                ` * - Ativos apenas\n` +
                ` *\n` +
                ` * Avisos:\n` +
                ` * - Sem paginacao\n` +
                ` *\n` +
                ` * Tabelas usadas: users\n` +
                ` */\n\n` +
                `SELECT id, name FROM users;`,
        );
    });

    it('formata cabecalho com # para alvos Python', () => {
        const code = 'users = select(User)';
        const result = formatDownloadContent({
            target: 'sqlalchemy',
            code,
            assumptions: ['User model mapeado'],
            warnings: [],
            tablesUsed: ['users'],
            labels,
        });

        expect(result).toBe(
            `# Premissas:\n` +
                `# - User model mapeado\n` +
                `#\n` +
                `# Tabelas usadas: users\n\n` +
                `users = select(User)`,
        );
    });

    it('omite secoes vazias mantendo apenas as preenchidas', () => {
        const code = 'const users = await db.select().from(usersTable);';
        const result = formatDownloadContent({
            target: 'drizzle',
            code,
            assumptions: [],
            warnings: ['Verificar indices'],
            tablesUsed: [],
            labels,
        });

        expect(result).toBe(
            `/*\n` +
                ` * Avisos:\n` +
                ` * - Verificar indices\n` +
                ` */\n\n` +
                `const users = await db.select().from(usersTable);`,
        );
    });
});
