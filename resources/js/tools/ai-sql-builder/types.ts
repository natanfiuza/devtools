/**
 * Contrato do AI SQL Builder (docs/SPEC.md secao 6, docs/API.md).
 *
 * Os nomes acompanham o JSON da API: `schema_source`, `output_target`,
 * `needs_clarification`. A tela nao inventa uma segunda lingua para o mesmo
 * dado - o que muda e so a apresentacao.
 */

export type SchemaSource = 'ddl' | 'json' | 'migration' | 'models';

export type SqlDialect = 'mysql' | 'pgsql' | 'sqlsrv' | 'sqlite';

/** Estado do lado do servidor. */
export type QueryStatus = 'queued' | 'processing' | 'done' | 'failed';

/** Estado da tela, que acrescenta o que so o cliente sabe. */
export type QueryViewStatus = 'idle' | 'submitting' | QueryStatus | 'timeout';

/** Por que a conta nao pode gerar agora (ordem do servidor). */
export type BlockReason =
    | 'guest'
    | 'email_unverified'
    | 'account_suspended'
    | 'quota_exhausted'
    | 'unavailable';

export interface Quota {
    unit: 'uses' | 'tokens';
    limit: number;
    used: number;
    remaining: number;
    exhausted: boolean;
    window: {
        start: string | null;
        end: string | null;
    };
    next_credit_at?: string | null;
}

export interface OutputTarget {
    slug: string;
    /** Identificacao do dado; o texto visivel e `ai.targets.<slug>`. */
    label: string;
    language: string;
    dialects: SqlDialect[];
}

/**
 * O pacote que o `ToolController` envia em `toolProps` (ADR-043).
 *
 * Ele descreve o estado **antes** de qualquer acao. O POST valida tudo de
 * novo: estas props economizam uma ida ao servidor para explicar o bloqueio,
 * nao substituem autorizacao.
 */
export interface AiToolProps {
    available: boolean;
    canUse: {
        allowed: boolean;
        reason: BlockReason | null;
    };
    quota: Quota | null;
    targets: OutputTarget[];
    sources: SchemaSource[];
    dialects: SqlDialect[];
    /** O menor entre o teto tecnico e o limite do plano. */
    limits: {
        schema: number;
        description: number;
    };
    poll: {
        initialMs: number;
        maxMs: number;
        timeoutMs: number;
    };
    endpoints: {
        store: string;
        /** Marcador `__ID__` que o cliente troca pelo ULID. */
        show: string;
        history?: string;
    };
}

export interface AiHistoryItem {
    id: string;
    title: string | null;
    status: QueryStatus;
    dialect: SqlDialect;
    output_target: string;
    schema_source: SchemaSource;
    schema: string | null;
    description: string | null;
    result: AiQueryResult | null;
    created_at: string | null;
}

export interface AiQueryResult {
    title?: string | null;
    explanation?: string | null;
    sql: string;
    dialect: SqlDialect;
    assumptions: string[];
    warnings: string[];
    tables_used: string[];
    needs_clarification: boolean;
    clarification: string | null;
    /** Saida por alvo (`sql`, `eloquent`, ...). */
    outputs: Record<string, string>;
}

export interface TokenUsage {
    in: number | null;
    out: number | null;
}

/** O `data` de `POST /api/ai/queries` e de `GET /api/ai/queries/{id}`. */
export interface AiQueryResponse {
    id: string;
    status: QueryStatus;
    cache_hit: boolean;
    result: AiQueryResult | null;
    tokens: TokenUsage;
    poll_after_ms: number | null;
    quota: Quota | null;
    error_code: string | null;
}

export interface AiQueryInput {
    schema_source: SchemaSource;
    dialect: SqlDialect;
    output_target: string;
    schema: string;
    description: string;
    locale?: string;
}

export interface AiQueryEndpoints {
    store: string;
    show: string;
}

export interface PollTiming {
    initialMs: number;
    maxMs: number;
    timeoutMs: number;
}
