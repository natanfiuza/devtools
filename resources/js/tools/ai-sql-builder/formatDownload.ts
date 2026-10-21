/**
 * Formatador do conteudo para download no AI SQL Builder.
 *
 * Gera um cabecalho de comentarios no topo do arquivo contendo as
 * premissas, avisos e tabelas utilizadas, formatado de acordo com a
 * sintaxe de comentario de cada linguagem/alvo.
 */

export interface FormatDownloadOptions {
    target: string;
    code: string;
    assumptions?: string[];
    warnings?: string[];
    tablesUsed?: string[];
    labels: {
        assumptions: string;
        warnings: string;
        tables: string;
    };
}

const PYTHON_TARGETS = new Set(['sqlmodel', 'sqlalchemy', 'django_orm']);

export function formatDownloadContent(options: FormatDownloadOptions): string {
    const {
        target,
        code,
        assumptions = [],
        warnings = [],
        tablesUsed = [],
        labels,
    } = options;

    const sections: string[] = [];

    if (assumptions.length > 0) {
        const lines = [labels.assumptions + ':'];
        for (const item of assumptions) {
            lines.push(`- ${item}`);
        }
        sections.push(lines.join('\n'));
    }

    if (warnings.length > 0) {
        const lines = [labels.warnings + ':'];
        for (const item of warnings) {
            lines.push(`- ${item}`);
        }
        sections.push(lines.join('\n'));
    }

    if (tablesUsed.length > 0) {
        sections.push(`${labels.tables}: ${tablesUsed.join(', ')}`);
    }

    if (sections.length === 0) {
        return code;
    }

    const commentBody = sections.join('\n\n');

    if (PYTHON_TARGETS.has(target)) {
        const lines = commentBody
            .split('\n')
            .map((line) => (line ? `# ${line}` : '#'));
        return `${lines.join('\n')}\n\n${code}`;
    }

    // Para SQL, PHP (eloquent, laravel_query_builder), TypeScript (drizzle, typeorm), Prisma:
    // Bloco no padrao C-style /* ... */
    const lines = commentBody
        .split('\n')
        .map((line) => (line ? ` * ${line}` : ' *'));
    return `/*\n${lines.join('\n')}\n */\n\n${code}`;
}
