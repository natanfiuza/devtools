import { MSSQL, MySQL, PostgreSQL, SQLite, type SQLDialect } from '@codemirror/lang-sql';
import type { SqlLanguage } from 'sql-formatter';

/**
 * Os quatro dialetos do SPEC, e os dois nomes que cada um tem.
 *
 * O slug e o do **glossario** (`mysql`, `pgsql`, `sqlsrv`, `sqlite`) - o mesmo
 * vocabulario do AI SQL Builder (secao 6 do SPEC), para que "pgsql" queira dizer
 * a mesma coisa nas duas telas. Nem a biblioteca de formatacao nem o realce do
 * editor usam esses nomes: o `sql-formatter` chama o dialeto do SQL Server de
 * `transactsql` (e aceita `tsql`), e o `@codemirror/lang-sql` tem um objeto de
 * dialeto por banco. O mapa e a unica linha que sabe disso.
 *
 * A traducao para o nome da biblioteca e fixa de proposito: o `language` do
 * `sql-formatter` tambem aceita `tsql` e `postgres`, e escolher um dos dois
 * seria escolher a dedo um apelido que a proxima versao pode mudar.
 */

export const DIALECTS = ['mysql', 'pgsql', 'sqlsrv', 'sqlite'] as const;

export type Dialect = (typeof DIALECTS)[number];

/** O dialeto de quem abre a tela: e o mais comum, e a tela nao lembra escolha. */
export const DEFAULT_DIALECT: Dialect = 'mysql';

interface DialectSpec {
    /** O nome que o `sql-formatter` entende. */
    language: SqlLanguage;
    /** O dialeto do realce do editor. */
    highlighter: SQLDialect;
}

const SPECS: Record<Dialect, DialectSpec> = {
    mysql: { language: 'mysql', highlighter: MySQL },
    pgsql: { language: 'postgresql', highlighter: PostgreSQL },
    sqlsrv: { language: 'transactsql', highlighter: MSSQL },
    sqlite: { language: 'sqlite', highlighter: SQLite },
};

export function languageOf(dialect: Dialect): SqlLanguage {
    return SPECS[dialect].language;
}

/**
 * A linguagem do editor.
 *
 * Todos os dialetos reais entram, mas o editor **monta a linguagem uma vez** e
 * nao a troca em uso - trocar de dialeto nao reconstroi o realce. Reconstruir
 * custaria o historico de desfazer e a posicao do cursor de quem esta no meio
 * de uma consulta, e o que o dialeto muda - a **formatacao** - esta do lado
 * direito, ao vivo. O que fica sem efeito imediato e so a cor de um token
 * isolado (o `#` que e comentario no MySQL, o `[coluna]` do SQL Server).
 */
export function highlighterOf(dialect: Dialect): SQLDialect {
    return SPECS[dialect].highlighter;
}
